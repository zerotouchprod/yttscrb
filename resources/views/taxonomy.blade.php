@extends('layouts.app')

@section('title', $taxonomy->name() . ' Transcripts & AI Summaries — TubeSum')
@section('meta_description', 'Browse ' . $taxonomy->videoCount() . ' video transcripts and AI summaries tagged "' . $taxonomy->name() . '". Free, no signup.')
@section('canonical', url('/' . $taxonomy->type()->routePrefix() . '/' . $taxonomy->slug()))
@section('og_title', $taxonomy->name() . ' — TubeSum')
@section('og_description', 'Browse ' . $taxonomy->videoCount() . ' video transcripts and AI summaries tagged "' . $taxonomy->name() . '".')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-400 mb-6">
        <a href="/" class="hover:text-white transition-colors">Home</a>
        <span class="mx-2">→</span>
        <a href="{{ url('/topics') }}" class="hover:text-white transition-colors">Topics</a>
        <span class="mx-2">→</span>
        <span class="text-gray-200">{{ $taxonomy->name() }}</span>
    </nav>

    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">{{ $taxonomy->name() }}</h1>
        <p class="text-gray-400">{{ $taxonomy->videoCount() }} transcript{{ $taxonomy->videoCount() !== 1 ? 's' : '' }}</p>
    </div>

    @if(empty($tasks))
        <p class="text-gray-400 text-center py-12">No transcripts found for this {{ $taxonomy->type()->label() === 'Speaker' ? 'speaker' : 'topic' }}.</p>
    @else
        <div class="grid gap-4">
            @foreach($tasks as $task)
                <a href="{{ url('/v/' . $task->slug) }}"
                   class="block bg-gray-800/60 rounded-xl p-5 border border-gray-700 hover:border-gray-500 transition-colors">
                    <h2 class="text-lg font-semibold text-white line-clamp-2 mb-1">{{ $task->title ?? 'Untitled' }}</h2>
                    <p class="text-xs text-gray-500">{{ $task->completed_at ? \Carbon\Carbon::parse($task->completed_at)->diffForHumans() : '' }}</p>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($total > $perPage)
            <div class="flex justify-center gap-4 mt-8">
                @if($page > 1)
                    <a href="?page={{ $page - 1 }}" class="text-sm text-gray-400 hover:text-white transition-colors">← Previous</a>
                @endif
                @if($page * $perPage < $total)
                    <a href="?page={{ $page + 1 }}" class="text-sm text-gray-400 hover:text-white transition-colors">Next →</a>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection
