@props(['id' => 'confirmationModal'])

<x-modals.base-modal :id="$id" title="" size="md">
    <div>
        <h3 id="{{ $id }}Title" class="text-xl font-bold text-gray-900 mb-4">Confirm Action</h3>
        <p id="{{ $id }}Message" class="text-gray-700 mb-6">Are you sure you want to proceed?</p>

        <div class="flex justify-end space-x-3">
            <button type="button" onclick="close{{ Str::studly($id) }}Modal()"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                Cancel
            </button>
            <button type="button" id="{{ $id }}ActionBtn" onclick="execute{{ Str::studly($id) }}Action()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                Confirm
            </button>
        </div>
    </div>
</x-modals.base-modal>

@push('scripts')
    <script>
        let {{ $id }}Callback = null;

        function show{{ Str::studly($id) }}(title, message, callback, buttonText = 'Confirm', buttonClass =
            'bg-blue-600 hover:bg-blue-700') {
            document.getElementById('{{ $id }}Title').textContent = title;
            document.getElementById('{{ $id }}Message').textContent = message;

            const actionBtn = document.getElementById('{{ $id }}ActionBtn');
            actionBtn.textContent = buttonText;
            actionBtn.className = `${buttonClass} text-white px-6 py-2 rounded-lg`;

            {{ $id }}Callback = callback;
            document.getElementById('{{ $id }}').classList.remove('hidden');
        }

        function close{{ Str::studly($id) }}Modal() {
            document.getElementById('{{ $id }}').classList.add('hidden');
            {{ $id }}Callback = null;
        }

        function execute{{ Str::studly($id) }}Action() {
            if ({{ $id }}Callback) {
                {{ $id }}Callback();
            }
            close{{ Str::studly($id) }}Modal();
        }
    </script>
@endpush
