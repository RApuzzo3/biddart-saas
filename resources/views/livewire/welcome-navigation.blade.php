<nav class="flex flex-1 flex-col items-center justify-center px-6 py-4 lg:px-8">
    <div class="hidden lg:flex lg:flex-1 lg:justify-end">
        @if (Route::has('login'))
            <a href="{{ route('login') }}" class="rounded-md bg-[#FF2D20] px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#FF2D20]/80 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#FF2D20]">Log in</a>
        @endif
    </div>
</nav>
