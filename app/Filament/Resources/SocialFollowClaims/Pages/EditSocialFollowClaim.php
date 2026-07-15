<?php

namespace App\Filament\Resources\SocialFollowClaims\Pages;

use App\Filament\Resources\SocialFollowClaims\SocialFollowClaimResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSocialFollowClaim extends EditRecord
{
    protected static string $resource = SocialFollowClaimResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        $claim = $this->record;
        $oldStatus = $this->oldData['status'] ?? null;
        $newStatus = $claim->status;

        if ($oldStatus === 'pending' && $newStatus === 'approved') {
            $claim->approve($this->data['admin_notes'] ?? null);
            Notification::make()
                ->title('Claim disetujui')
                ->body('Customer '.$claim->user->name.' mendapat reward '.$claim->reward_tier)
                ->success()
                ->send();
        } elseif ($oldStatus === 'pending' && $newStatus === 'rejected') {
            $claim->reject($this->data['admin_notes'] ?? null);
            Notification::make()
                ->title('Claim ditolak')
                ->body('Claim dari '.$claim->user->name.' ditolak')
                ->warning()
                ->send();
        }
    }
}
