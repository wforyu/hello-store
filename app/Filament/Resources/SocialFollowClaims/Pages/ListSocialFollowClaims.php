<?php

namespace App\Filament\Resources\SocialFollowClaims\Pages;

use App\Filament\Resources\SocialFollowClaims\SocialFollowClaimResource;
use App\Models\SocialFollowClaim;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSocialFollowClaims extends ListRecords
{
    protected static string $resource = SocialFollowClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveAll')
                ->label('Approve Semua Pending')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Semua Claim?')
                ->modalDescription('Semua claim dengan status "Menunggu" akan disetujui.')
                ->action(function () {
                    $pendingClaims = SocialFollowClaim::pending()->get();
                    $count = 0;
                    foreach ($pendingClaims as $claim) {
                        $claim->approve();
                        $count++;
                    }
                    Notification::make()
                        ->title("Berhasil approve {$count} claim")
                        ->success()
                        ->send();
                }),
        ];
    }
}
