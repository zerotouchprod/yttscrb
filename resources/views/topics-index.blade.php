@extends('layouts.app')

@section('title', 'All Topics — TubeSum')
@section('meta_description', 'Browse all topics with free AI-powered video transcripts and summaries. No signup required.')
@section('canonical', url('/topics'))
@section('og_title', 'All Topics — TubeSum')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <nav class="text-sm text-gray-400 mb-6">
        <a href="/" class="hover:text-white transition-colors">Home</a>
        <span class="mx-2">→</span>
        <span class="text-gray-200">Topics</span>
    </nav>

    <h1 class="text-3xl font-bold text-white mb-8">All Topics</h1>

    @if(empty($topics))
        <p class="text-gray-400 text-center py-12">No topics yet. Transcribe a video to get started!</p>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($topics as $topic)
                <a href="{{ url('/topic/' . $topic->slug()) }}"
                   class="block bg-gray-800/60 rounded-xl p-4 border border-gray-700 hover:border-gray-500 hover:bg-gray-700/40 transition-all">
                    <span class="text-sm font-medium text-white block truncate">#{{ $topic->name() }}</span>
                    <span class="text-xs text-gray-500 mt-1">{{ $topic->videoCount() }} video{{ $topic->videoCount() !== 1 ? 's' : '' }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
