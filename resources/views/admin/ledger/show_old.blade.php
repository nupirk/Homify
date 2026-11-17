@extends('layouts.admin')

@section('title', 'Ledger Entry Details')

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Ledger Entry #{{ $entry->id }}</h1>
            <a href="{{ route('admin.ledger.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="row">
            <!-- Entry Details -->
            <div class="col-md-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Entry Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">Entry ID:</th>
                                <td>{{ $entry->id }}</td>
                            </tr>
                            <tr>
                                <th>Created Date:</th>
                                <td>{{ $entry->created_at->format('F j, Y \a\t g:i A') }}</td>
                            </tr>
                            <tr>
                                <th>Updated Date:</th>
                                <td>{{ $entry->updated_at->format('F j, Y \a\t g:i A') }}</td>
                            </tr>
                            <tr>
                                <th>Booking ID:</th>
                                <td>
                                    @if ($entry->booking_id)
                                        <a href="{{ route('admin.ledger.order', $entry->booking_id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            View Order #{{ $entry->booking_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">Not associated with any booking</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>User:</th>
                                <td>
                                    @if ($entry->user)
                                        <div>
                                            <strong>{{ $entry->user->first_name }} {{ $entry->user->last_name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $entry->user->email }}</small>
                                            <br>
                                            <a href="{{ route('admin.ledger.user', $entry->user_id) }}"
                                                class="btn btn-sm btn-outline-info mt-1">
                                                View User Ledger
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-muted">User #{{ $entry->user_id }} (Deleted)</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Entry Type:</th>
                                <td>
                                    <span
                                        class="badge badge-{{ $entry->type == 'CUSTOMER_DEBIT'
                                            ? 'danger'
                                            : ($entry->type == 'PARTNER_EARNING'
                                                ? 'success'
                                                : ($entry->type == 'PLATFORM_FEE'
                                                    ? 'primary'
                                                    : ($entry->type == 'TAX_DEDUCTION'
                                                        ? 'warning'
                                                        : 'secondary'))) }} badge-lg">
                                        {{ str_replace('_', ' ', $entry->type) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Amount:</th>
                                <td>
                                    <h4 class="text-{{ $entry->amount < 0 ? 'danger' : 'success' }}">
                                        Rs {{ number_format($entry->amount, 2) }}
                                    </h4>
                                </td>
                            </tr>
                            <tr>
                                <th>Balance After:</th>
                                <td>
                                    @if ($entry->balance_after !== null)
                                        <h5 class="text-{{ $entry->balance_after < 0 ? 'danger' : 'success' }}">
                                            Rs {{ number_format($entry->balance_after, 2) }}
                                        </h5>
                                    @else
                                        <span class="text-muted">Not applicable</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Narration:</th>
                                <td>
                                    <div class="alert alert-light border">
                                        {{ $entry->narration ?: 'No description provided' }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Related Information -->
            <div class="col-md-4">
                @if ($entry->booking_id)
                    <!-- Booking Related Entries -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Related Order Entries</h6>
                        </div>
                        <div class="card-body">
                            @if ($relatedEntries->count() > 0)
                                @foreach ($relatedEntries as $related)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span
                                                class="badge badge-{{ $related->type == 'CUSTOMER_DEBIT'
                                                    ? 'danger'
                                                    : ($related->type == 'PARTNER_EARNING'
                                                        ? 'success'
                                                        : ($related->type == 'PLATFORM_FEE'
                                                            ? 'primary'
                                                            : 'secondary')) }}">
                                                {{ str_replace('_', ' ', $related->type) }}
                                            </span>
                                            <strong>Rs {{ number_format($related->amount, 2) }}</strong>
                                        </div>
                                        <small class="text-muted">
                                            {{ $related->user ? $related->user->first_name . ' ' . $related->user->last_name : 'User #' . $related->user_id }}
                                        </small>
                                        @if ($related->id != $entry->id)
                                            <div class="mt-1">
                                                <a href="{{ route('admin.ledger.show', $related->id) }}"
                                                    class="btn btn-xs btn-outline-primary">View</a>
                                            </div>
                                        @else
                                            <div class="mt-1">
                                                <span class="badge badge-info">Current Entry</span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <p class="text-muted">No other entries for this booking</p>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($entry->user)
                    <!-- User Balance Summary -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">User Balance Summary</h6>
                        </div>
                        <div class="card-body">
                            @php
                                $currentBalance = \App\Models\LedgerEntry::getUserBalance($entry->user_id);
                                $entryCount = \App\Models\LedgerEntry::where('user_id', $entry->user_id)->count();
                            @endphp

                            <div class="text-center">
                                <h4 class="text-{{ $currentBalance < 0 ? 'danger' : 'success' }}">
                                    Rs {{ number_format($currentBalance, 2) }}
                                </h4>
                                <small class="text-muted">Current Balance</small>
                            </div>

                            <hr>

                            <div class="text-center">
                                <strong>{{ number_format($entryCount) }}</strong>
                                <br>
                                <small class="text-muted">Total Entries</small>
                            </div>

                            <div class="mt-3">
                                <a href="{{ route('admin.ledger.user', $entry->user_id) }}"
                                    class="btn btn-primary btn-block">
                                    View User Ledger
                                </a>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @if ($entry->booking_id)
                                <a href="{{ route('admin.ledger.order', $entry->booking_id) }}"
                                    class="list-group-item list-group-item-action">
                                    <i class="fas fa-shopping-cart"></i> View Order Ledger
                                </a>
                            @endif

                            @if ($entry->user)
                                <a href="{{ route('admin.ledger.user', $entry->user_id) }}"
                                    class="list-group-item list-group-item-action">
                                    <i class="fas fa-user"></i> View User Ledger
                                </a>
                            @endif

                            <a href="{{ route('admin.ledger.index', ['type' => $entry->type]) }}"
                                class="list-group-item list-group-item-action">
                                <i class="fas fa-filter"></i> View Similar Entries
                            </a>

                            <a href="{{ route('admin.ledger.index', ['date_from' => $entry->created_at->format('Y-m-d'), 'date_to' => $entry->created_at->format('Y-m-d')]) }}"
                                class="list-group-item list-group-item-action">
                                <i class="fas fa-calendar"></i> View Same Day Entries
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

