@extends('layouts.admin')

@section('title', 'Order Ledger')

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Order Ledger: #{{ $bookingId }}</h1>
            <div>
                <a href="{{ route('admin.ledger.export', ['booking_id' => $bookingId]) }}" class="btn btn-success btn-sm">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <a href="{{ route('admin.ledger.index') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <style>
            .timeline {
                position: relative;
                padding: 0;
            }

            .timeline-item {
                position: relative;
                padding: 15px 0;
                border-left: 3px solid #dee2e6;
                margin-left: 15px;
                padding-left: 25px;
            }

            .timeline-item:before {
                content: '';
                position: absolute;
                left: -8px;
                top: 20px;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #6c757d;
            }

            .timeline-credit {
                border-left-color: #28a745;
            }

            .timeline-credit:before {
                background: #28a745;
            }

            .timeline-debit {
                border-left-color: #dc3545;
            }

            .timeline-debit:before {
                background: #dc3545;
            }

            .timeline-time {
                font-size: 0.8rem;
                color: #6c757d;
                margin-bottom: 5px;
            }

            .timeline-content {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 5px;
                border: 1px solid #dee2e6;
            }
        </style>admin')

    @section('title', 'Order Ledger')

    @section('content')
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Order Ledger: #{{ $bookingId }}</h1>
                <div>
                    <a href="{{ route('super-admin.ledger.export', ['booking_id' => $bookingId]) }}"
                        class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                    <a href="{{ route('super-admin.ledger.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Order Summary -->
                <div class="col-md-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Order Summary</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <th>Order ID:</th>
                                    <td>{{ $bookingId }}</td>
                                </tr>
                                <tr>
                                    <th>Total Entries:</th>
                                    <td><span class="badge badge-info">{{ number_format($stats['total_entries']) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Users Involved:</th>
                                    <td><span
                                            class="badge badge-secondary">{{ number_format($stats['unique_users']) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td><strong>Rs {{ number_format($stats['total_amount'], 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <th>First Entry:</th>
                                    <td>
                                        @if ($stats['first_entry'])
                                            {{ \Carbon\Carbon::parse($stats['first_entry'])->format('M j, Y g:i A') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Last Entry:</th>
                                    <td>
                                        @if ($stats['last_entry'])
                                            {{ \Carbon\Carbon::parse($stats['last_entry'])->format('M j, Y g:i A') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Transaction Breakdown -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Transaction Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <th>Customer Paid:</th>
                                    <td><strong>Rs {{ number_format($breakdown['customer_paid'], 2) }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Tax Amount:</th>
                                    <td>Rs {{ number_format($breakdown['tax_amount'], 2) }}</td>
                                </tr>
                                <tr>
                                    <th>Service Amount:</th>
                                    <td>Rs {{ number_format($breakdown['service_amount'], 2) }}</td>
                                </tr>
                                <tr>
                                    <th>Partner Earning:</th>
                                    <td class="text-success">Rs {{ number_format($breakdown['partner_earning'], 2) }}</td>
                                </tr>
                                <tr>
                                    <th>Platform Fee:</th>
                                    <td class="text-primary">Rs {{ number_format($breakdown['platform_fee'], 2) }}</td>
                                </tr>
                                <tr>
                                    <th>Commission Rate:</th>
                                    <td>{{ $breakdown['commission_rate'] }}%</td>
                                </tr>
                                @if (abs($breakdown['fee_variance']) > 0.01)
                                    <tr>
                                        <th>Fee Variance:</th>
                                        <td class="text-warning">Rs {{ number_format($breakdown['fee_variance'], 2) }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </div> <!-- Double-Entry Validation -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Double-Entry Validation</h6>
                        </div>
                        <div class="card-body">
                            @php
                                $totalDebits = isset($pageTotals['total_debits']) ? $pageTotals['total_debits'] : 0;
                                $totalCredits = isset($pageTotals['total_credits']) ? $pageTotals['total_credits'] : 0;
                                $isBalanced = abs($totalDebits + $totalCredits) < 0.01; // Account for floating point precision
                            @endphp

                            <div class="row text-center">
                                <div class="col-6">
                                    <h5 class="text-danger">Rs {{ number_format(abs($totalDebits), 2) }}</h5>
                                    <small class="text-muted">Total Debits</small>
                                </div>
                                <div class="col-6">
                                    <h5 class="text-success">Rs {{ number_format($totalCredits, 2) }}</h5>
                                    <small class="text-muted">Total Credits</small>
                                </div>
                            </div>

                            <hr>

                            <div class="text-center">
                                @if ($isBalanced)
                                    <span class="badge badge-success badge-lg">
                                        <i class="fas fa-check"></i> Balanced
                                    </span>
                                    <br>
                                    <small class="text-muted">Debits = Credits</small>
                                @else
                                    <span class="badge badge-danger badge-lg">
                                        <i class="fas fa-exclamation-triangle"></i> Unbalanced
                                    </span>
                                    <br>
                                    <small class="text-danger">
                                        Difference: Rs {{ number_format(abs($totalDebits + $totalCredits), 2) }}
                                    </small>
                                @endif
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
                                                    : ($typeStats->type == 'PLATFORM_FEE'
                                                        ? 'primary'
                                                        : 'secondary')) }}">
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
                    <!-- Transaction Flow -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Transaction Flow</h6>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                @foreach ($transactionFlow as $flow)
                                    <div
                                        class="timeline-item {{ $flow['is_debit'] ? 'timeline-debit' : 'timeline-credit' }}">
                                        <div class="timeline-time">
                                            {{ \Carbon\Carbon::parse($flow['timestamp'])->format('H:i:s') }}
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span
                                                        class="badge badge-{{ $flow['type'] == 'CUSTOMER_DEBIT' ? 'danger' : ($flow['type'] == 'PARTNER_EARNING' ? 'success' : ($flow['type'] == 'PLATFORM_FEE' ? 'primary' : 'secondary')) }}">
                                                        {{ str_replace('_', ' ', $flow['type']) }}
                                                    </span>
                                                    <p class="mb-1">{{ $flow['user_name'] }}</p>
                                                    @if ($flow['narration'])
                                                        <small class="text-muted">{{ $flow['narration'] }}</small>
                                                    @endif
                                                </div>
                                                <div class="text-right">
                                                    <div
                                                        class="font-weight-bold {{ $flow['is_debit'] ? 'text-danger' : 'text-success' }}">
                                                        {{ $flow['is_debit'] ? '-' : '+' }}Rs
                                                        {{ number_format(abs($flow['amount']), 2) }}
                                                    </div>
                                                    <small class="text-muted">Balance: Rs
                                                        {{ number_format($flow['running_balance'], 2) }}</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">All Entries for Order #{{ $bookingId }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Entry ID</th>
                                            <th>Date & Time</th>
                                            <th>User</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Balance After</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($ledgerEntries as $entry)
                                            <tr>
                                                <td>{{ $entry->id }}</td>
                                                <td>
                                                    {{ $entry->created_at->format('M j, Y') }}
                                                    <br>
                                                    <small
                                                        class="text-muted">{{ $entry->created_at->format('g:i:s A') }}</small>
                                                </td>
                                                <td>
                                                    @if ($entry->user)
                                                        <a href="{{ route('super-admin.ledger.user', $entry->user_id) }}"
                                                            class="text-primary">
                                                            {{ $entry->user->first_name }} {{ $entry->user->last_name }}
                                                        </a>
                                                        <br>
                                                        <small class="text-muted">{{ $entry->user->email }}</small>
                                                    @else
                                                        <span class="text-muted">User #{{ $entry->user_id }}</span>
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
                                                                    : ($entry->type == 'TAX_DEDUCTION'
                                                                        ? 'warning'
                                                                        : 'secondary'))) }}">
                                                        {{ str_replace('_', ' ', $entry->type) }}
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <span class="text-{{ $entry->amount < 0 ? 'danger' : 'success' }}">
                                                        <strong>Rs {{ number_format($entry->amount, 2) }}</strong>
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    @if ($entry->balance_after !== null)
                                                        <span
                                                            class="text-{{ $entry->balance_after < 0 ? 'danger' : 'success' }}">
                                                            Rs {{ number_format($entry->balance_after, 2) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <small>{{ Str::limit($entry->narration, 50) }}</small>
                                                </td>
                                                <td>
                                                    <a href="{{ route('super-admin.ledger.show', $entry->id) }}"
                                                        class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">No ledger entries found
                                                    for
                                                    this order</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if ($ledgerEntries->count() > 0)
                                        <tfoot>
                                            <tr class="table-info font-weight-bold">
                                                <td colspan="4" class="text-right"><strong>Total:</strong></td>
                                                <td class="text-right">
                                                    <strong>Rs {{ number_format($pageTotals['total_amount'], 2) }}</strong>
                                                </td>
                                                <td colspan="3"></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-right">Debits:</td>
                                                <td class="text-right text-danger">
                                                    <strong>Rs {{ number_format($pageTotals['total_debits'], 2) }}</strong>
                                                </td>
                                                <td colspan="3"></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-right">Credits:</td>
                                                <td class="text-right text-success">
                                                    <strong>Rs
                                                        {{ number_format($pageTotals['total_credits'], 2) }}</strong>
                                                </td>
                                                <td colspan="3"></td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction Flow Visualization -->
                    @if ($ledgerEntries->count() > 0)
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Transaction Flow</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @foreach ($ledgerEntries->groupBy('user_id') as $userId => $userEntries)
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-left-info">
                                                <div class="card-body py-2">
                                                    <h6 class="mb-2">
                                                        @if ($userEntries->first()->user)
                                                            {{ $userEntries->first()->user->first_name }}
                                                            {{ $userEntries->first()->user->last_name }}
                                                        @else
                                                            User #{{ $userId }}
                                                        @endif
                                                    </h6>

                                                    @foreach ($userEntries as $entry)
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-1">
                                                            <span
                                                                class="badge badge-{{ $entry->type == 'CUSTOMER_DEBIT' ? 'danger' : ($entry->type == 'PARTNER_EARNING' ? 'success' : 'primary') }}">
                                                                {{ str_replace('_', ' ', $entry->type) }}
                                                            </span>
                                                            <span
                                                                class="text-{{ $entry->amount < 0 ? 'danger' : 'success' }}">
                                                                Rs {{ number_format($entry->amount, 2) }}
                                                            </span>
                                                        </div>
                                                    @endforeach

                                                    <hr class="my-2">

                                                    <div class="d-flex justify-content-between">
                                                        <strong>Net Impact:</strong>
                                                        @php
                                                            $netAmount = $userEntries->sum('amount');
                                                        @endphp
                                                        <strong class="text-{{ $netAmount < 0 ? 'danger' : 'success' }}">
                                                            Rs {{ number_format($netAmount, 2) }}
                                                        </strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Timeline -->
                    @if ($ledgerEntries->count() > 0)
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Transaction Timeline</h6>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    @foreach ($ledgerEntries->sortBy('created_at') as $entry)
                                        <div class="timeline-item">
                                            <div
                                                class="timeline-marker bg-{{ $entry->type == 'CUSTOMER_DEBIT' ? 'danger' : ($entry->type == 'PARTNER_EARNING' ? 'success' : 'primary') }}">
                                            </div>
                                            <div class="timeline-content">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="mb-1">{{ str_replace('_', ' ', $entry->type) }}</h6>
                                                    <small
                                                        class="text-muted">{{ $entry->created_at->format('g:i:s A') }}</small>
                                                </div>
                                                <p class="mb-1">
                                                    <strong class="text-{{ $entry->amount < 0 ? 'danger' : 'success' }}">
                                                        Rs {{ number_format($entry->amount, 2) }}
                                                    </strong>
                                                    @if ($entry->user)
                                                        for {{ $entry->user->first_name }} {{ $entry->user->last_name }}
                                                    @endif
                                                </p>
                                                <small class="text-muted">{{ $entry->narration }}</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endsection

    @section('styles')
        <style>
            .timeline {
                position: relative;
                padding-left: 30px;
            }

            .timeline::before {
                content: '';
                position: absolute;
                left: 15px;
                top: 0;
                bottom: 0;
                width: 2px;
                background: #dee2e6;
            }

            .timeline-item {
                position: relative;
                margin-bottom: 20px;
            }

            .timeline-marker {
                position: absolute;
                left: -23px;
                top: 5px;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                border: 2px solid #fff;
                box-shadow: 0 0 0 2px #dee2e6;
            }

            .timeline-content {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #dee2e6;
            }
        </style>
    @endsection
