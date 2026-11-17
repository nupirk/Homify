@extends('layouts.admin')

@section('title', 'User Ledger')

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                User Ledger: {{ $user ? $user->first_name . ' ' . $user->last_name : 'User #' . $userId }}
            </h1>
            <div>
                @if ($user && $user->partnerProfile)
                    <a href="{{ route('admin.ledger.partner.statement-form', $user->user_id) }}"
                        class="btn btn-primary btn-sm">
                        <i class="fas fa-file-pdf"></i> Generate Statement
                    </a>
                @endif
                <a href="{{ route('admin.ledger.export', array_merge(request()->query(), ['user_id' => $user->user_id ?? $userId])) }}"
                    class="btn btn-success btn-sm">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <a href="{{ route('admin.ledger.balances') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Balances
                </a>
            </div>
        </div>

        <div class="row">
            <!-- User Info -->
            <div class="col-md-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
                    </div>
                    <div class="card-body">
                        @if ($user)
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <th>Name:</th>
                                    <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td>{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td>{{ $user->phone ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Joined:</th>
                                    <td>{{ $user->created_at->format('M j, Y') }}</td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        @if ($user->email_verified_at)
                                            <span class="badge badge-success">Verified</span>
                                        @else
                                            <span class="badge badge-warning">Unverified</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @else
                            <div class="alert alert-warning">
                                <strong>User Not Found</strong><br>
                                This user (ID: {{ $userId }}) may have been deleted, but their ledger entries are
                                preserved.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Balance Summary -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Balance Summary</h6>
                    </div>
                    <div class="card-body text-center">
                        <h3
                            class="text-{{ $currentBalance < 0 ? 'danger' : ($currentBalance > 0 ? 'success' : 'muted') }}">
                            Rs {{ number_format($currentBalance, 2) }}
                        </h3>
                        <small class="text-muted">Current Balance</small>

                        <hr>

                        <div class="row text-center">
                            <div class="col-6">
                                <strong>{{ number_format($stats['total_entries']) }}</strong>
                                <br>
                                <small class="text-muted">Total Entries</small>
                            </div>
                            <div class="col-6">
                                <strong>{{ number_format($stats['unique_bookings']) }}</strong>
                                <br>
                                <small class="text-muted">Unique Orders</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Entry Type Breakdown -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Entry Breakdown</h6>
                    </div>
                    <div class="card-body">
                        @foreach ($stats['by_type'] as $typeStats)
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span
                                        class="badge badge-{{ $typeStats->type == 'CUSTOMER_DEBIT'
                                            ? 'danger'
                                            : ($typeStats->type == 'PARTNER_EARNING'
                                                ? 'success'
                                                : 'primary') }}">
                                        {{ str_replace('_', ' ', $typeStats->type) }}
                                    </span>
                                    <strong>{{ number_format($typeStats->count) }}</strong>
                                </div>
                                <small class="text-muted">Total: Rs {{ number_format($typeStats->total, 2) }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Ledger Entries -->
            <div class="col-md-8">
                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('admin.ledger.user', $userId) }}">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="booking_id">Booking ID</label>
                                    <input type="text" name="booking_id" id="booking_id" class="form-control"
                                        value="{{ request('booking_id') }}" placeholder="Order ID">
                                </div>

                                <div class="col-md-3">
                                    <label for="type">Entry Type</label>
                                    <select name="type" id="type" class="form-control">
                                        <option value="">All Types</option>
                                        @foreach ($entryTypes as $type)
                                            <option value="{{ $type }}"
                                                {{ request('type') == $type ? 'selected' : '' }}>
                                                {{ str_replace('_', ' ', $type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="date_from">Date From</label>
                                    <input type="date" name="date_from" id="date_from" class="form-control"
                                        value="{{ request('date_from') }}">
                                </div>

                                <div class="col-md-3">
                                    <label for="date_to">Date To</label>
                                    <input type="date" name="date_to" id="date_to" class="form-control"
                                        value="{{ request('date_to') }}">
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary form-control">Filter</button>
                                </div>
                                <div class="col-md-2">
                                    <a href="{{ route('admin.ledger.user', $userId) }}"
                                        class="btn btn-secondary form-control">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Entries Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Ledger Entries</h6>
                        <small class="text-muted">Running balance shown</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Order ID</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Balance</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($ledgerEntries as $entry)
                                        <tr>
                                            <td>
                                                <small>{{ $entry->created_at->format('M j, Y') }}</small>
                                                <br>
                                                <small class="text-muted">{{ $entry->created_at->format('g:i A') }}</small>
                                            </td>
                                            <td>
                                                @if ($entry->booking_id)
                                                    <a href="{{ route('admin.ledger.order', $entry->booking_id) }}"
                                                        class="text-primary">{{ $entry->booking_id }}</a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-{{ $entry->type == 'CUSTOMER_DEBIT'
                                                        ? 'danger'
                                                        : ($entry->type == 'PARTNER_EARNING'
                                                            ? 'success'
                                                            : ($entry->type == 'PLATFORM_FEE'
                                                                ? 'primary'
                                                                : 'secondary')) }}">
                                                    {{ str_replace('_', ' ', $entry->type) }}
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <span class="text-{{ $entry->amount < 0 ? 'danger' : 'success' }}">
                                                    Rs {{ number_format($entry->amount, 2) }}
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                @if ($entry->balance_after !== null)
                                                    <strong
                                                        class="text-{{ $entry->balance_after < 0 ? 'danger' : 'success' }}">
                                                        Rs {{ number_format($entry->balance_after, 2) }}
                                                    </strong>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ Str::limit($entry->narration, 40) }}</small>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.ledger.show', $entry->id) }}"
                                                    class="btn btn-xs btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No ledger entries found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                Showing {{ $ledgerEntries->firstItem() ?? 0 }} to {{ $ledgerEntries->lastItem() ?? 0 }}
                                of {{ $ledgerEntries->total() }} entries
                            </div>
                            {{ $ledgerEntries->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Auto-submit form when select changes
        document.getElementById('type').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
@endsection
