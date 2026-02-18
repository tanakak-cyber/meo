<div>
    <label class="block text-sm font-semibold text-gray-700 mb-2">{{ $label }}</label>
    <div class="flex gap-3 flex-wrap">
        @foreach($options as $option)
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $option }}"
                       class="peer sr-only"
                       onchange="this.closest('label').classList.toggle('selected', this.checked)">
                <span class="px-4 py-2.5 text-sm font-medium rounded-lg border-2 border-gray-300 bg-white text-gray-700 transition-all duration-200 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 hover:bg-gray-50 peer-checked:hover:bg-indigo-700 peer-focus:ring-2 peer-focus:ring-indigo-500 peer-focus:ring-offset-2">
                    {{ $option }}
                </span>
            </label>
        @endforeach
    </div>
</div>

