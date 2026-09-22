<x-layout :title="$note->title">
    <header class="mb-10 border-b border-line pb-6">
        <h1 class="text-3xl font-bold leading-tight text-white">{{ $note->title }}</h1>
        <p class="mt-2 text-sm text-muted">
            Updated <time datetime="{{ $note->updated_at->toIso8601String() }}">{{ $note->updated_at->format('F j, Y') }}</time>
        </p>
    </header>

    <article class="note">
        {!! $note->html !!}
    </article>
</x-layout>
