<div class="mt-4 flex flex-col gap-3">
    @if (session('login_error'))
        <p role="alert" class="text-sm text-danger-600 dark:text-danger-400">{{ session('login_error') }}</p>
    @endif

    <a href="{{ $url }}" class="fi-btn fi-btn-color-gray fi-size-md w-full justify-center rounded-lg px-3 py-2 text-sm font-semibold ring-1 ring-gray-950/10 dark:ring-white/20">
        {{ __('auth.google_login') }}
    </a>
</div>
