@if (session('status'))
    <x-ui.toast :message="session('status')" />
@endif
<x-ui.errors />
