<?php

namespace App\Filament\Resources\ChatConversations\Pages;

use App\Filament\Resources\ChatConversations\ChatConversationResource;
use App\Models\ChatConversation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListChatConversations extends ListRecords
{
    protected static string $resource = ChatConversationResource::class;

    public static function getLabel(): string
    {
        return 'Live Chat';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_label')
                    ->label('Pelanggan')
                    ->searchable(['id', 'guest_name', 'user.name'])
                    ->sortable(),
                TextColumn::make('messages_count')
                    ->label('Pesan')
                    ->counts('messages')
                    ->sortable(),
                TextColumn::make('last_message_at')
                    ->label('Pesan Terakhir')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('unread_customer_messages_count')
                    ->label('Belum Dibalas')
                    ->counts('unreadCustomerMessages')
                    ->color(fn ($state) => ($state ?? 0) > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => ($state ?? 0) > 0 ? "{$state} balasan" : 'Terbalas'),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->searchPlaceholder('Cari pelanggan...')
            ->recordActions([
                Action::make('view_chat')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ChatConversation $record) => 'Percakapan '.$record->customer_label)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (ChatConversation $record) => view('filament.resources.chat.chat-modal', ['conversation' => $record])),
                Action::make('reply')
                    ->label('Balas')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->modalHeading(fn (ChatConversation $record) => 'Balas ke '.$record->customer_label)
                    ->form([
                        Textarea::make('reply_message')
                            ->label('Pesan Balasan')
                            ->required()
                            ->rows(4)
                            ->maxLength(1000)
                            ->placeholder('Ketik balasan Anda...'),
                    ])
                    ->action(function (array $data, ChatConversation $record, Action $action): void {
                        $record->messages()->where('sender_type', 'customer')
                            ->whereNull('read_at')
                            ->update(['read_at' => now()]);
                        $record->messages()->create([
                            'user_id' => auth()->id(),
                            'sender_type' => 'admin',
                            'message' => $data['reply_message'],
                        ]);
                        $record->update(['last_message_at' => now()]);
                        $action->modal()->close();
                        Notification::make()
                            ->title('Balasan terkirim')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
