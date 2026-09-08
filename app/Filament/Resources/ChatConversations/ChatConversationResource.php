<?php

namespace App\Filament\Resources\ChatConversations;

use App\Filament\Resources\ChatConversations\Pages\ListChatConversations;
use App\Models\ChatConversation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class ChatConversationResource extends Resource
{
    protected static ?string $model = ChatConversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeftEllipsis;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Live Chat';

    protected static ?string $modelLabel = 'Percakapan';

    protected static ?string $pluralModelLabel = 'Live Chat';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pesanan';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChatConversations::route('/'),
        ];
    }
}
