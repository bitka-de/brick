@extends('layouts.app')

@section('content')
<div class="bg-white rounded-lg shadow-lg p-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ $title ?? 'Dashboard' }}</h1>
    
    <!-- Statistics Cards -->
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @foreach($stats ?? [] as $key => $value)
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">{{ ucwords(str_replace('_', ' ', $key)) }}</p>
                    <p class="text-2xl font-bold">{{ $value }}</p>
                </div>
                <div class="bg-white bg-opacity-20 rounded-full p-3">
                    @if($key === 'total_users')
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                        </svg>
                    @elseif($key === 'active_sessions')
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15.586 13V12a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                    @elseif($key === 'server_load')
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"></path>
                        </svg>
                    @else
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    <!-- Recent Activities -->
    @if($activities ?? false)
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Activities</h2>
        <div class="space-y-4">
            @foreach($activities as $activity)
            <div class="flex items-center justify-between border-b border-gray-200 pb-4 last:border-b-0 last:pb-0">
                <div class="flex items-center space-x-3">
                    <div class="bg-gray-100 p-2 rounded-full">
                        <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $activity['action'] }}</p>
                        <p class="text-xs text-gray-500">by {{ $activity['user'] }}</p>
                    </div>
                </div>
                <span class="text-xs text-gray-400">{{ $activity['time'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<!-- Quick Actions -->
<div class="mt-8 grid md:grid-cols-3 gap-6">
    <div class="bg-white rounded-lg shadow-lg p-6 text-center">
        <div class="bg-green-100 p-4 rounded-full w-16 h-16 mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Create New</h3>
        <p class="text-gray-600 text-sm mb-4">Add new content or resources</p>
        <button class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors">
            Get Started
        </button>
    </div>
    
    <div class="bg-white rounded-lg shadow-lg p-6 text-center">
        <div class="bg-blue-100 p-4 rounded-full w-16 h-16 mx-auto mb-4">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Analytics</h3>
        <p class="text-gray-600 text-sm mb-4">View detailed reports and insights</p>
        <a href="/api/info" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
            View Reports
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-lg p-6 text-center">
        <div class="bg-purple-100 p-4 rounded-full w-16 h-16 mx-auto mb-4">
            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Settings</h3>
        <p class="text-gray-600 text-sm mb-4">Configure system preferences</p>
        <button class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition-colors">
            Manage
        </button>
    </div>
</div>
@endsection