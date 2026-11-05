{{-- Define before Alpine starts; navigation must not depend on module download order. --}}
<script @if ($nonce = \Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ $nonce }}" @endif>
    document.documentElement.classList.remove('no-js');
    window.tgNavigation = () => ({
        mobileOpen: false,
        init() {
            this.desktop = window.matchMedia('(min-width: 1024px)');
            this.onResize = () => { if (this.desktop.matches) this.mobileOpen = false; };
            this.onKeydown = (event) => {
                if (!this.mobileOpen || event.key !== 'Tab' || !this.$root.querySelector('[data-drawer]')) return;
                const nodes = [...this.$root.querySelector('[data-navigation]').querySelectorAll('a[href], button:not([disabled])')]
                    .filter(node => node.getClientRects().length);
                if (!nodes.length) return;
                const first = nodes[0], last = nodes[nodes.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            };
            this.desktop.addEventListener('change', this.onResize);
            window.addEventListener('keydown', this.onKeydown);
        },
        toggle() {
            if (this.mobileOpen) { this.close(); return; }
            this.mobileOpen = true;
            this.$nextTick(() => this.$root.querySelector('[data-navigation] a[href], [data-navigation] button')?.focus());
        },
        close(restoreFocus = true) {
            if (!this.mobileOpen) return;
            this.mobileOpen = false;
            if (restoreFocus) this.$nextTick(() => this.$root.querySelector('[data-nav-toggle]')?.focus());
        },
        destroy() {
            this.desktop.removeEventListener('change', this.onResize);
            window.removeEventListener('keydown', this.onKeydown);
        }
    });
</script>
