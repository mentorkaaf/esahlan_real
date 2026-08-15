<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $posting->title }} — Careers at eSahlan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { navy: '#1B1444', brand: '#F7941D' } } } }</script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">

    <header class="bg-navy py-6 px-4">
        <div class="max-w-3xl mx-auto flex items-center justify-between">
            <div class="text-2xl font-bold text-white">e<span class="text-brand">Sahlan</span></div>
            <a href="{{ route('careers.index') }}" class="text-blue-200 text-sm hover:text-white">← All Jobs</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-10">

        @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-3 gap-6">
            {{-- Job details --}}
            <div class="col-span-2 space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $posting->title }}</h1>
                    <div class="flex flex-wrap gap-2 mt-3">
                        @if($posting->department)
                        <span class="bg-navy/10 text-navy text-xs px-2.5 py-1 rounded-full">{{ $posting->department->name }}</span>
                        @endif
                        <span class="bg-gray-100 text-gray-600 text-xs px-2.5 py-1 rounded-full">{{ $posting->getTypeLabel() }}</span>
                        <span class="bg-gray-100 text-gray-600 text-xs px-2.5 py-1 rounded-full">{{ $posting->location }}</span>
                        <span class="bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full">{{ $posting->spots_left }} opening{{ $posting->spots_left != 1 ? 's' : '' }}</span>
                    </div>

                    @if($posting->description)
                    <div class="mt-5 text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $posting->description }}</div>
                    @endif

                    @if($posting->requirements)
                    <div class="mt-5">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Requirements</h3>
                        <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $posting->requirements }}</div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Application form --}}
            <div>
                <div class="bg-white rounded-xl border border-gray-200 p-5 sticky top-6">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Apply for this position</h2>

                    <form method="POST" action="{{ route('careers.apply', $posting) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        {{-- Honeypot --}}
                        <div class="hidden" aria-hidden="true">
                            <input type="text" name="_trap" tabindex="-1" autocomplete="off" value="">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('name') border-red-400 @enderror">
                            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" name="email" value="{{ old('email') }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('email') border-red-400 @enderror">
                            @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Phone</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">CV / Resume * (PDF, DOC, max 5MB)</label>
                            <input type="file" name="cv" required accept=".pdf,.doc,.docx"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-navy file:text-white hover:file:bg-navy/80">
                            @error('cv')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit"
                                class="w-full bg-brand hover:bg-orange-600 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                            Submit Application
                        </button>

                        <p class="text-xs text-gray-400 text-center">We'll contact you within 5 business days.</p>
                    </form>
                </div>
            </div>
        </div>

    </main>

</body>
</html>
