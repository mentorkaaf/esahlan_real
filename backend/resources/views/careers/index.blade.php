<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers — eSahlan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { navy: '#1B1444', brand: '#F7941D' } } } }</script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

    {{-- Header --}}
    <header class="bg-navy py-12 text-center">
        <div class="max-w-2xl mx-auto px-4">
            <div class="text-4xl font-bold text-white mb-2">e<span class="text-brand">Sahlan</span></div>
            <h1 class="text-2xl font-semibold text-white mt-4">Careers at eSahlan</h1>
            <p class="text-blue-200 mt-2 text-sm">Join our team and help shape the future of e-commerce in Somalia.</p>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-12">

        @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
        @endif

        @if($postings->isEmpty())
        <div class="text-center py-20 text-gray-400">
            <p class="text-lg">No open positions at this time.</p>
            <p class="text-sm mt-2">Check back soon — we're growing fast!</p>
        </div>
        @else
        <div class="space-y-4">
            @foreach($postings as $posting)
            <a href="{{ route('careers.show', $posting) }}"
               class="block bg-white rounded-xl border border-gray-200 p-6 hover:shadow-md hover:border-brand/30 transition-all">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ $posting->title }}</h2>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @if($posting->department)
                            <span class="bg-navy/10 text-navy text-xs px-2.5 py-1 rounded-full">{{ $posting->department->name }}</span>
                            @endif
                            <span class="bg-gray-100 text-gray-600 text-xs px-2.5 py-1 rounded-full">{{ $posting->getTypeLabel() }}</span>
                            <span class="bg-gray-100 text-gray-600 text-xs px-2.5 py-1 rounded-full">{{ $posting->location }}</span>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                            {{ $posting->spots_left }} spot{{ $posting->spots_left != 1 ? 's' : '' }} left
                        </span>
                        @if($posting->closes_at)
                        <div class="text-xs text-gray-400 mt-1">Closes {{ $posting->closes_at->format('d M Y') }}</div>
                        @endif
                    </div>
                </div>
                @if($posting->description)
                <p class="mt-3 text-sm text-gray-600 line-clamp-2">{{ $posting->description }}</p>
                @endif
                <div class="mt-4 text-brand text-sm font-medium">Apply now →</div>
            </a>
            @endforeach
        </div>
        @endif

    </main>

</body>
</html>
