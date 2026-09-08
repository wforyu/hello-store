<div style="display:flex;flex-direction:column;gap:14px;max-height:60vh;overflow-y:auto;padding:4px;">
    @if($conversation->messages->isEmpty())
        <div style="text-align:center;color:var(--gray-400);padding:24px 0;">Belum ada pesan.</div>
    @endif

    @foreach($conversation->messages as $msg)
        @if($msg->sender_type === 'admin')
            <div>
                <div style="margin-bottom:4px;font-size:11px;font-weight:600;color:var(--gray-500);">Anda (Admin)</div>
                <div style="display:inline-block;max-width:20rem;padding:10px 12px;border-radius:12px;border-bottom-left-radius:4px;background:var(--gray-100);color:var(--gray-800);font-size:13px;text-align:left;">
                    {{ $msg->message }}
                    <div style="margin-top:4px;font-size:10px;color:var(--gray-400);">{{ $msg->created_at->format('d M H:i') }}</div>
                </div>
            </div>
        @else
            <div style="text-align:right;">
                <div style="margin-bottom:4px;font-size:11px;font-weight:600;color:var(--primary-600);">{{ $conversation->customer_label }}</div>
                <div style="display:inline-block;max-width:20rem;padding:10px 12px;border-radius:12px;border-bottom-right-radius:4px;background:var(--primary-500);color:#fff;font-size:13px;text-align:left;">
                    {{ $msg->message }}
                    <div style="margin-top:4px;font-size:10px;opacity:0.8;">{{ $msg->created_at->format('d M H:i') }}</div>
                </div>
            </div>
        @endif
    @endforeach
</div>