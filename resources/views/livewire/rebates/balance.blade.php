<x-ui.panel title="Saldo rebate — semua akun">
@if(!$balance['has_entries'])<p>Belum ada rebate yang dikonfirmasi. Estimasi broker tidak dihitung sebagai saldo.</p>@endif
<p>Saldo tersedia: <strong>{{ \App\Actions\Rebates\Finance::usd($balance['available']) }}</strong></p><p>Total rebate bersih: {{ \App\Actions\Rebates\Finance::usd($balance['credited']) }} · Total lot: {{ $balance['lots'] }}</p><p>Masih ditahan: {{ \App\Actions\Rebates\Finance::usd($balance['reserved']) }} · Sudah dibayar: {{ \App\Actions\Rebates\Finance::usd($balance['paid']) }}</p>
</x-ui.panel>
