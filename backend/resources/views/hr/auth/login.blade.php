<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Login — eSahlan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { navy: '#1B1444', brand: '#F7941D' } } } }</script>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1B1444] to-[#2D2467] flex items-center justify-center p-4">

    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-[#F7941D] rounded-2xl mb-4 shadow-lg">
                <span class="text-white font-black text-2xl">e</span>
            </div>
            <h1 class="text-white text-2xl font-bold">eSahlan HR</h1>
            <p class="text-white/50 text-sm mt-1">Sign in to the HR panel</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-8">
            @if($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
                {{ $errors->first() }}
            </div>
            @endif

            @if(session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
                {{ session('error') }}
            </div>
            @endif

            <form method="POST" action="{{ route('hr.login.submit') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444] focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <input type="password" name="password" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444] focus:border-transparent">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember" class="rounded border-gray-300">
                    <label for="remember" class="text-sm text-gray-600">Remember me</label>
                </div>

                <button type="submit"
                        class="w-full bg-[#1B1444] hover:bg-[#2D2467] text-white font-semibold py-2.5 rounded-lg transition-colors text-sm">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-white/30 text-xs mt-6">eSahlan HR Panel · {{ date('Y') }}</p>
    </div>

</body>
</html>
