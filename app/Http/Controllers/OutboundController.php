<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CheckoutSettings;
use App\Models\Broker;
use App\Models\OutboundClick;
use App\Support\PublicContent;
use Illuminate\Http\Request;

final class OutboundController
{
    public function __invoke(Request $request)
    {
        $target = $request->route('target');
        $id = $request->route('id');
        if ($target === 'telegram') {
            $url = app(CheckoutSettings::class)->telegram();
        } else {
            $broker = Broker::where('is_active', true)->where('valid_from', '<=', now('Asia/Jakarta')->toDateString())->findOrFail($id);
            $url = PublicContent::brokerLink($broker->ib_link);
        }
        abort_unless($url, 404);
        (new OutboundClick)->forceFill(['target_type' => $target, 'target_id' => $id, 'visitor_hash' => hash_hmac('sha256', (string) request()->ip(), config('app.key'))])->save();

        return redirect()->away($url)->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
