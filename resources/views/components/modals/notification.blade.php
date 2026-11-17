@props(['id' => 'notificationModal'])

<x-modals.base-modal :id="$id" title="" size="sm">
    <div class="text-center">
        <div id="{{ $id }}Icon" class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full">
            <!-- Icon will be populated by JavaScript -->
        </div>
        <h3 id="{{ $id }}Title" class="text-xl font-bold mb-4">Notification</h3>
        <p id="{{ $id }}Message" class="text-gray-700 mb-6">Message will appear here</p>
        <button type="button" onclick="close{{ Str::studly($id) }}Modal()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
            OK
        </button>
    </div>
</x-modals.base-modal>

@push('scripts')
    <script>
        function show{{ Str::studly($id) }}(type, title, message, callback = null) {
            document.getElementById('{{ $id }}Title').textContent = title;
            document.getElementById('{{ $id }}Message').textContent = message;

            const iconDiv = document.getElementById('{{ $id }}Icon');
            const titleElement = document.getElementById('{{ $id }}Title');

            if (type === 'success') {
                iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-green-100';
                iconDiv.innerHTML = '<i class="fas fa-check text-2xl text-green-600"></i>';
                titleElement.className = 'text-xl font-bold text-green-800 mb-4';
            } else if (type === 'error') {
                iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100';
                iconDiv.innerHTML = '<i class="fas fa-times text-2xl text-red-600"></i>';
                titleElement.className = 'text-xl font-bold text-red-800 mb-4';
            } else {
                iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-blue-100';
                iconDiv.innerHTML = '<i class="fas fa-info text-2xl text-blue-600"></i>';
                titleElement.className = 'text-xl font-bold text-blue-800 mb-4';
            }

            document.getElementById('{{ $id }}').classList.remove('hidden');

            if (callback) {
                setTimeout(() => {
                    close{{ Str::studly($id) }}Modal();
                    callback();
                }, 2000);
            }
        }

        function close{{ Str::studly($id) }}Modal() {
            document.getElementById('{{ $id }}').classList.add('hidden');
        }
    </script>
@endpush
