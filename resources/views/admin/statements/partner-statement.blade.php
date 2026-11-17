<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Partner Statement - {{ $data['partner']->first_name }} {{ $data['partner']->last_name }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin: 0;
        }

        .document-title {
            font-size: 18px;
            color: #4b5563;
            margin: 5px 0;
        }

        .statement-period {
            font-size: 14px;
            color: #6b7280;
            margin: 10px 0;
        }

        .partner-info {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .partner-info h3 {
            margin: 0 0 15px 0;
            color: #1f2937;
            font-size: 16px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-item {
            margin: 5px 0;
        }

        .info-label {
            font-weight: bold;
            color: #4b5563;
        }

        .balance-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .balance-card {
            background-color: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .balance-card.positive {
            border-color: #10b981;
            background-color: #f0fdf4;
        }

        .balance-card.negative {
            border-color: #ef4444;
            background-color: #fef2f2;
        }

        .balance-amount {
            font-size: 20px;
            font-weight: bold;
            margin: 5px 0;
        }

        .balance-amount.positive {
            color: #059669;
        }

        .balance-amount.negative {
            color: #dc2626;
        }

        .balance-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .summary-section {
            background-color: #f9fafb;
            padding: 20px;
            border-radius: 8px;
        }

        .summary-section h4 {
            margin: 0 0 15px 0;
            color: #1f2937;
            font-size: 14px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px dotted #d1d5db;
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #d1d5db;
        }

        .transactions-section {
            margin-top: 30px;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 12px;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .transactions-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
            text-transform: uppercase;
            font-size: 11px;
        }

        .transaction-type {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .type-earning {
            background-color: #d1fae5;
            color: #065f46;
        }

        .type-commission {
            background-color: #fef3c7;
            color: #92400e;
        }

        .type-settlement {
            background-color: #e5e7eb;
            color: #374151;
        }

        .type-withdrawal {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .amount-positive {
            color: #059669;
            font-weight: bold;
        }

        .amount-negative {
            color: #dc2626;
            font-weight: bold;
        }

        .monthly-breakdown {
            margin-top: 30px;
        }

        .month-card {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .month-header {
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .month-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            font-size: 12px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }

        .page-break {
            page-break-before: always;
        }

        @media print {

            .balance-summary,
            .summary-grid,
            .info-grid,
            .month-stats {
                display: block !important;
            }

            .balance-card,
            .summary-section,
            .month-card {
                margin-bottom: 15px;
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <h1 class="company-name">Urban Services Platform</h1>
        <h2 class="document-title">Partner Financial Statement</h2>
        <p class="statement-period">
            Period: {{ $data['period']['from']->format('F j, Y') }} - {{ $data['period']['to']->format('F j, Y') }}
            ({{ $data['period']['days'] }} days)
        </p>
        <p style="font-size: 12px; color: #6b7280;">Generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Partner Information -->
    <div class="partner-info">
        <h3>Partner Information</h3>
        <div class="info-grid">
            <div>
                <div class="info-item">
                    <span class="info-label">Name:</span> {{ $data['partner']->first_name }}
                    {{ $data['partner']->last_name }}
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span> {{ $data['partner']->email }}
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span> {{ $data['partner']->phone ?? 'N/A' }}
                </div>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Partner ID:</span> {{ $data['partner']->user_id }}
                </div>
                <div class="info-item">
                    <span class="info-label">Joined:</span> {{ $data['partner']->created_at->format('F j, Y') }}
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    {{ ucfirst($data['partner']->partner_application_status ?? 'Active') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Balance Summary -->
    <div class="balance-summary">
        <div class="balance-card {{ $data['balances']['opening'] >= 0 ? 'positive' : 'negative' }}">
            <div class="balance-label">Opening Balance</div>
            <div class="balance-amount {{ $data['balances']['opening'] >= 0 ? 'positive' : 'negative' }}">
                NPR {{ number_format($data['balances']['opening'], 2) }}
            </div>
        </div>

        <div class="balance-card {{ $data['balances']['closing'] >= 0 ? 'positive' : 'negative' }}">
            <div class="balance-label">Closing Balance</div>
            <div class="balance-amount {{ $data['balances']['closing'] >= 0 ? 'positive' : 'negative' }}">
                NPR {{ number_format($data['balances']['closing'], 2) }}
            </div>
        </div>

        <div class="balance-card {{ $data['balances']['net_change'] >= 0 ? 'positive' : 'negative' }}">
            <div class="balance-label">Net Change</div>
            <div class="balance-amount {{ $data['balances']['net_change'] >= 0 ? 'positive' : 'negative' }}">
                {{ $data['balances']['net_change'] >= 0 ? '+' : '' }}NPR
                {{ number_format($data['balances']['net_change'], 2) }}
            </div>
        </div>

        <div class="balance-card {{ $data['balances']['current_available'] >= 0 ? 'positive' : 'negative' }}">
            <div class="balance-label">Current Available</div>
            <div class="balance-amount {{ $data['balances']['current_available'] >= 0 ? 'positive' : 'negative' }}">
                NPR {{ number_format($data['balances']['current_available'], 2) }}
            </div>
        </div>
    </div>

    <!-- Period Summary -->
    <div class="summary-grid">
        <div class="summary-section">
            <h4>Period Activity Summary</h4>
            <div class="summary-item">
                <span>Total Earnings ({{ $data['summary']['period']['earnings_count'] }} jobs)</span>
                <span class="amount-positive">+NPR
                    {{ number_format($data['summary']['period']['total_earnings'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>COD Commission ({{ $data['summary']['period']['cod_jobs_count'] }} jobs)</span>
                <span class="amount-negative">-NPR
                    {{ number_format($data['summary']['period']['total_cod_commission'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>Withdrawals ({{ $data['summary']['period']['withdrawal_count'] }} transactions)</span>
                <span class="amount-negative">-NPR
                    {{ number_format($data['summary']['period']['total_withdrawals'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>Net Balance Change</span>
                <span
                    class="{{ $data['summary']['period']['net_earnings'] >= 0 ? 'amount-positive' : 'amount-negative' }}">
                    {{ $data['summary']['period']['net_earnings'] >= 0 ? '+' : '' }}NPR
                    {{ number_format($data['summary']['period']['net_earnings'], 2) }}
                </span>
            </div>
        </div>

        <div class="summary-section">
            <h4>Payment Method Breakdown</h4>
            <div class="summary-item">
                <span>Online Jobs ({{ $data['breakdown']['payment_methods']['online']['jobs_count'] }})</span>
                <span class="amount-positive">+NPR
                    {{ number_format($data['breakdown']['payment_methods']['online']['earnings_received'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>COD Jobs ({{ $data['breakdown']['payment_methods']['cod']['jobs_count'] }})</span>
                <span class="amount-negative">-NPR
                    {{ number_format($data['breakdown']['payment_methods']['cod']['commission_deducted'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>Avg Online Earning</span>
                <span>NPR {{ number_format($data['breakdown']['payment_methods']['online']['avg_earning'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>Avg COD Commission</span>
                <span>NPR {{ number_format($data['breakdown']['payment_methods']['cod']['avg_commission'], 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Analytics -->
    <div class="summary-grid">
        <div class="summary-section">
            <h4>Performance Analytics</h4>
            <div class="summary-item">
                <span>Avg Earning Per Job</span>
                <span>NPR {{ number_format($data['analytics']['avg_earning_per_job'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>Effective Commission Rate</span>
                <span>{{ number_format($data['analytics']['commission_rate'], 1) }}%</span>
            </div>
            <div class="summary-item">
                <span>Total Jobs Completed</span>
                <span>{{ $data['summary']['period']['earnings_count'] + $data['summary']['period']['cod_jobs_count'] }}</span>
            </div>
        </div>

        <div class="summary-section">
            <h4>All-Time Summary</h4>
            <div class="summary-item">
                <span>Total Lifetime Earnings</span>
                <span class="amount-positive">NPR
                    {{ number_format($data['summary']['all_time']['total_earnings'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>Total COD Commission</span>
                <span class="amount-negative">NPR
                    {{ number_format($data['summary']['all_time']['total_cod_commission'], 2) }}</span>
            </div>
            <div class="summary-item">
                <span>Total Withdrawals</span>
                <span>NPR {{ number_format($data['summary']['all_time']['total_withdrawals'], 2) }}</span>
            </div>
        </div>
    </div>

    @if ($data['transactions']['ledger_entries']->count() > 0)
        <!-- Transaction Details -->
        <div class="transactions-section page-break">
            <h3>Transaction Details</h3>
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Order ID</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['transactions']['ledger_entries'] as $entry)
                        <tr>
                            <td>{{ $entry->created_at->format('M j, Y') }}</td>
                            <td>
                                <span
                                    class="transaction-type
                            @if ($entry->type === 'PARTNER_EARNING') type-earning
                            @elseif($entry->type === 'PAS_RECEIPT') type-earning
                            @elseif($entry->type === 'PAS_COMMISSION') type-commission
                            @elseif($entry->type === 'PAS_CASH_SETTLEMENT') type-settlement
                            @elseif($entry->type === 'WITHDRAWAL') type-withdrawal @endif">
                                    {{ str_replace('_', ' ', $entry->type) }}
                                </span>
                            </td>
                            <td>{{ $entry->booking_id ?? '-' }}</td>
                            <td style="font-size: 10px; max-width: 200px;">{{ Str::limit($entry->narration, 100) }}
                            </td>
                            <td
                                class="{{ in_array($entry->type, ['PARTNER_EARNING', 'PAS_RECEIPT']) ? 'amount-positive' : 'amount-negative' }}">
                                {{ in_array($entry->type, ['PARTNER_EARNING', 'PAS_RECEIPT']) ? '+' : '-' }}NPR
                                {{ number_format($entry->amount, 2) }}
                            </td>
                            <td class="{{ $entry->balance_after >= 0 ? 'amount-positive' : 'amount-negative' }}">
                                NPR {{ number_format($entry->balance_after, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($data['transactions']['recent_payouts']->count() > 0)
        <!-- Recent Payouts -->
        <div class="transactions-section">
            <h3>Payouts During Period</h3>
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['transactions']['recent_payouts'] as $payout)
                        <tr>
                            <td>{{ $payout->created_at->format('M j, Y') }}</td>
                            <td class="amount-negative">NPR {{ number_format($payout->amount, 2) }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $payout->payment_method)) }}</td>
                            <td>
                                <span
                                    class="transaction-type
                            @if ($payout->status === 'completed') type-earning
                            @elseif($payout->status === 'pending') type-commission
                            @else type-withdrawal @endif">
                                    {{ ucfirst($payout->status) }}
                                </span>
                            </td>
                            <td>{{ $payout->transaction_reference ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This statement is generated automatically and contains all transactions for the specified period.</p>
        <p>For any discrepancies or questions, please contact our finance team.</p>
        <p><strong>Urban Services Platform</strong> | Statement generated on {{ now()->format('F j, Y \a\t g:i A') }}
        </p>
    </div>
</body>

</html>
