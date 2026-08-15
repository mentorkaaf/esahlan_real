@props([
    'name',
    'label',
    'type'        => 'text',
    'required'    => false,
    'value'       => null,
    'placeholder' => null,
    'step'        => null,
])
<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1.5">
        {{ $label }}
        @if($required) <span class="text-red-500">*</span> @endif
    </label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if($required) required @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($step) step="{{ $step }}" @endif
        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444] focus:border-transparent @error($name) border-red-400 @enderror"
    >
    @error($name)
    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
    @enderror
</div>
