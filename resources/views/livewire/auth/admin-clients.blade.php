<div class="auth-page">
    <div class="auth-admin">
        <div class="auth-admin-heading">
            <div>
                <p class="eyebrow">PANEL ADMIN / CLIENT</p>
                <h1>Kelola akses client<span>.</span></h1>
                <p class="auth-muted">Reset password hanya setelah permintaan melalui kontak terdaftar dan verifikasi identitas manual.</p>
            </div>
            <a class="auth-secondary" href="{{ route('admin') }}">Panel admin <x-ui.icon /></a>
        </div>
        @include('livewire.auth.partials.feedback')
        <section class="auth-card auth-client-panel" aria-labelledby="client-list-title">
            <div class="auth-client-toolbar">
                <div>
                    <h2 id="client-list-title">Daftar client</h2>
                    <p class="auth-hint">Menampilkan maksimal 20 akun terbaru sesuai pencarian.</p>
                </div>
                <div class="auth-search">
                    <x-auth-field name="search" label="Cari nama atau email" type="search" wire:model.live.debounce.350ms="search" maxlength="120" autocomplete="off" />
                    <x-ui.loading target="search" label="Mencari akun…" class="auth-hint" />
                </div>
            </div>
            <p class="auth-warning">Verifikasi ulang berlaku selama 10 menit untuk reset password. <a class="auth-text-link" href="{{ route('admin.reauth') }}">Verifikasi ulang admin</a></p>
            <ul class="auth-client-list" aria-label="Hasil pencarian client">
                @forelse ($clients as $client)
                    <li wire:key="client-{{ $client->id }}" class="auth-client-row">
                        <div>
                            <h3>{{ $client->name }}</h3>
                            <p class="auth-masked-email">{{ mb_substr($client->email, 0, 1).'***@'.\Illuminate\Support\Str::after($client->email, '@') }}</p>
                            <p class="auth-hint">
                                {{ $client->status->value === 'active' ? 'Akun aktif' : 'Akun dinonaktifkan' }}
                                @if ($client->must_change_password)
                                    · Wajib mengganti password
                                @endif
                            </p>
                        </div>
                        @can('resetPassword', $client)
                            <button type="button" class="auth-secondary" wire:click="openReset({{ $client->id }})" wire:loading.attr="disabled" wire:target="openReset({{ $client->id }})">
                                <span wire:loading.remove wire:target="openReset({{ $client->id }})">Reset password</span>
                                <span wire:loading wire:target="openReset({{ $client->id }})">Memeriksa akses…</span>
                            </button>
                        @else
                            <a class="auth-text-link" href="{{ route('admin.reauth') }}">Verifikasi untuk reset <x-ui.icon name="arrow-right" /></a>
                        @endcan
                    </li>
                @empty
                    <li class="auth-client-empty">Tidak ada akun client yang sesuai.</li>
                @endforelse
            </ul>
        </section>
    </div>

    @if ($target)
        <x-ui.modal id="reset" :title="'Reset akses '.$target->name.'?'" description="Seluruh sesi lama client diputus. Client harus mengganti password sementara saat masuk kembali. Tindakan dicatat dalam audit."
            wire:key="reset-modal-{{ $target->id }}" x-on:modal-cancel="$wire.cancelReset()" wire:loading.class="auth-is-busy" wire:target="resetPassword,cancelReset">
                <p class="auth-client-target">{{ mb_substr($target->email, 0, 1).'***@'.\Illuminate\Support\Str::after($target->email, '@') }}</p>
                @if ($target->contact_channel && $target->contact_value_enc)
                    <p class="auth-client-target">Kontak terdaftar ({{ $target->contact_channel->value === 'telegram' ? 'Telegram' : 'WhatsApp' }}): <strong>{{ $target->contact_value_enc }}</strong></p>
                @else
                    <p class="auth-warning">Kontak terdaftar belum tersedia. Reset tidak dapat diproses.</p>
                @endif
                @include('livewire.auth.partials.feedback')
                <form wire:submit="resetPassword" class="auth-form">
                    <div class="auth-field">
                        <label for="reason">Alasan reset</label>
                        <textarea id="reason" name="reason" class="auth-input" wire:model="reason" x-ref="resetReason" rows="3" maxlength="500" required aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}" aria-describedby="reason-hint @error('reason') reason-error @enderror"></textarea>
                        <p class="auth-hint" id="reason-hint">Cukup ringkasan pemeriksaan. Jangan menyalin bukti identitas, password, atau OTP.</p>
                        @error('reason')<p id="reason-error" class="auth-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="auth-checkbox" for="contactChecked"><input id="contactChecked" type="checkbox" wire:model="contactChecked" required @error('contact_checked') aria-describedby="contactChecked-error" aria-invalid="true" @enderror><span>Permintaan berasal dari kontak yang telah terdaftar pada akun.</span></label>
                        @error('contact_checked')<p id="contactChecked-error" class="auth-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="auth-checkbox" for="identityChecked"><input id="identityChecked" type="checkbox" wire:model="identityChecked" required @error('identity_checked') aria-describedby="identityChecked-error" aria-invalid="true" @enderror><span>Identitas telah diperiksa dengan bukti yang hanya diketahui pemilik, tanpa meminta password atau OTP.</span></label>
                        @error('identity_checked')<p id="identityChecked-error" class="auth-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="auth-modal-actions">
                        <button class="auth-secondary" type="button" wire:click="cancelReset" wire:loading.attr="disabled" wire:target="resetPassword,cancelReset">Batal</button>
                        <button class="button auth-danger" type="submit" wire:loading.attr="disabled" wire:target="resetPassword,cancelReset">
                            <span wire:loading.remove wire:target="resetPassword">Reset password client</span>
                            <span wire:loading wire:target="resetPassword" role="status">Mereset password…</span>
                        </button>
                    </div>
                </form>
        </x-ui.modal>
    @endif
</div>
