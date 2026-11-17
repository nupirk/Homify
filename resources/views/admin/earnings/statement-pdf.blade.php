<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Earnings Statement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }

        .statement-title {
            font-size: 18px;
            margin-top: 10px;
        }

        .date-range {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .summary-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .summary-item {
            text-align: center;
        }

        .summary-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .summary-value {
            font-size: 18px;
            font-weight: bold;
            margin-top: 5px;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .transactions-table th,
        .transactions-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }

        .transactions-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .transactions-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .amount {
            text-align: right;
            font-weight: bold;
        }

        .partner-share {
            color: #059669;
        }

        .company-share {
            color: #7c3aed;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        @media print {
            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">Urban Company</div>
        <div class="statement-title">Earnings Statement</div>
        @if (isset($date_from) || isset($date_to))
            <div class="date-range">
                @if ($date_from && $date_to)
                    Period: {{ date('M d, Y', strtotime($date_from)) }} - {{ date('M d, Y', strtotime($date_to)) }}
                @elseif($date_from)
                    From: {{ date('M d, Y', strtotime($date_from)) }}
                @elseif($date_to)
                    Until: {{ date('M d, Y', strtotime($date_to)) }}
                @endif
            </div>
        @endif
        <div class="date-range">Generated on: {{ date('M d, Y H:i:s') }}</div>
    </div>

    <div class="summary-section">
        <h3>Summary</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Transactions</div>
                <div class="summary-value">{{ number_format($transaction_count) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Gross Revenue</div>
                <div class="summary-value">₹{{ number_format($total_gross, 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label partner-share">Partner Earnings</div>
                <div class="summary-value partner-share">₹{{ number_format($total_partner_earnings, 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label company-share">Company Earnings</div>
                <div class="summary-value company-share">₹{{ number_format($total_platform_earnings, 2) }}</div>
            </div>
        </div>
    </div>

    <h3>Transaction Details</h3>
    <table class="transactions-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Partner</th>
                <th>Service</th>
                <th>Gross Amount</th>
                @if ($view_type == 'all' || $view_type == 'partners')
                    <th>Partner Share</th>
                @endif
                @if ($view_type == 'all' || $view_type == 'company')
                    <th>Company Share</th>
                @endif
                <th>Commission</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($earnings as $earning)
                <tr>
                    <td>{{ substr($earning->order_id, 0, 12) }}...</td>
                    <td>{{ $earning->calculated_at ? $earning->calculated_at->format('M d, Y') : 'Not calculated' }}
                    </td>
                    <td>{{ $earning->partner->user->first_name }} {{ $earning->partner->user->last_name }}</td>
                    <td>{{ $earning->order?->orderItems?->first()?->service?->name ?? 'N/A' }}</td>
                    <td class="amount">₹{{ number_format($earning->gross_amount, 2) }}</td>
                    @if ($view_type == 'all' || $view_type == 'partners')
                        <td class="amount partner-share">₹{{ number_format($earning->partner_share, 2) }}</td>
                    @endif
                    @if ($view_type == 'all' || $view_type == 'company')
                        <td class="amount company-share">₹{{ number_format($earning->platform_share, 2) }}</td>
                    @endif
                    <td>{{ number_format($earning->commissionRule->partner_pct, 1) }}% /
                        {{ number_format($earning->commissionRule->platform_pct, 1) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This statement was generated automatically by Urban Company's earnings management system.</p>
        <p>For any discrepancies or questions, please contact the finance department.</p>
    </div>
</body>

</html>
