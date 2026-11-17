@extends('layouts.site')

@section('title', 'Privacy Policy — Homify Services')
@section('description', 'How Homify Services collects, uses, shares and protects your personal data when you use the Homify app and website in Nepal.')
@section('canonical', config('app.public_url') . '/privacy')

@section('content')
    <div class="legal-hero">
        <div class="container">
            <h1>Privacy Policy</h1>
            <p>Your data protection &middot; Last updated January 2025</p>
        </div>
    </div>

    @php
        $renderBody = function ($content) {
            $out = '';
            $inList = false;
            foreach (preg_split('/\r?\n/', $content) as $line) {
                $line = trim($line);
                if ($line === '') {
                    if ($inList) { $out .= '</ul>'; $inList = false; }
                    continue;
                }
                if (str_starts_with($line, '• ')) {
                    if (!$inList) { $out .= '<ul>'; $inList = true; }
                    $out .= '<li>' . e(substr($line, 2)) . '</li>';
                } else {
                    if ($inList) { $out .= '</ul>'; $inList = false; }
                    $out .= '<p>' . e($line) . '</p>';
                }
            }
            if ($inList) { $out .= '</ul>'; }
            return $out;
        };

        $sections = [
            ['1. Introduction', "At Homify Services, we respect your privacy and are committed to protecting your personal data. This Privacy Policy explains how we collect, use, share, and protect your information when you use our mobile application and services. This policy applies to all users in Nepal and complies with applicable data protection laws."],
            ['2. Information We Collect', "We collect several types of information:\n\n• Personal Information: Name, email address, phone number, profile picture, date of birth, and gender.\n\n• Account Information: Username, password (encrypted), and account preferences.\n\n• Location Data: Your location when booking services, delivery addresses, and map usage data.\n\n• Payment Information: Payment card details (processed securely by our payment partners), transaction history, and billing information.\n\n• Service Data: Booking details, service preferences, ratings, reviews, and communication with service providers.\n\n• Device Information: Device type, operating system, unique device identifiers, IP address, and app usage data.\n\n• Communication Data: Messages with service providers and customer support interactions."],
            ['3. How We Use Your Information', "We use your information for the following purposes:\n\n• Service Delivery: To connect you with service providers, process bookings, and facilitate service completion.\n\n• Account Management: To create and maintain your account, verify your identity, and provide customer support.\n\n• Payments: To process payments, prevent fraud, and maintain financial records.\n\n• Communication: To send booking confirmations, service updates, promotional offers (with your consent), and important announcements.\n\n• Quality Improvement: To analyze service quality, gather feedback, improve our platform, and develop new features.\n\n• Safety & Security: To verify service providers, prevent fraudulent activities, and ensure platform security.\n\n• Legal Compliance: To comply with legal obligations, resolve disputes, and enforce our terms."],
            ['4. Information Sharing', "We share your information only in these circumstances:\n\n• Service Providers: We share necessary information with service partners to complete your bookings (name, contact, address, service details).\n\n• Payment Processors: Payment card information is shared with certified payment gateways for secure transaction processing.\n\n• Business Partners: With your consent, we may share anonymized data with partners for market research and improvement.\n\n• Legal Requirements: We may disclose information to comply with court orders, legal processes, or government requests.\n\n• Business Transfers: In case of merger, acquisition, or asset sale, your information may be transferred to the successor entity.\n\nWe do NOT sell your personal information to third parties for marketing purposes."],
            ['5. Data Security', "We implement industry-standard security measures to protect your data:\n\n• Encryption: All sensitive data is encrypted in transit (SSL/TLS) and at rest using AES-256 encryption.\n\n• Secure Storage: Data is stored on secure cloud servers with restricted access and regular security audits.\n\n• Access Controls: Employee access to personal data is limited and monitored. All staff sign confidentiality agreements.\n\n• Payment Security: We use PCI-DSS compliant payment processors. We never store full credit card numbers.\n\n• Regular Updates: We continuously update our security measures to address emerging threats.\n\nWhile we take security seriously, no system is 100% secure. Please protect your account credentials and report any suspicious activity immediately."],
            ['6. Location Data', "We collect location data to:\n\n• Match you with nearby service providers\n• Calculate service costs based on distance\n• Show accurate service arrival times\n• Improve service availability in your area\n\nYou can control location permissions in your device settings. Disabling location may limit some features but won't prevent you from using the core service."],
            ['7. Marketing Communications', "With your consent, we may send:\n\n• Promotional offers and discounts\n• New service announcements\n• Seasonal deals and special events\n• Tips and service recommendations\n\nYou can opt-out of marketing communications anytime through:\n• App notification settings\n• Profile > Settings > Notifications\n• Unsubscribe links in emails\n\nYou will still receive essential service-related communications (booking confirmations, service updates, account security alerts)."],
            ['8. Your Privacy Rights', "You have the right to:\n\n• Access: Request a copy of your personal data we hold.\n\n• Correction: Update or correct inaccurate information.\n\n• Deletion: Request deletion of your account and associated data (subject to legal retention requirements).\n\n• Data Portability: Request your data in a machine-readable format.\n\n• Withdraw Consent: Opt-out of marketing communications or data processing based on consent.\n\n• Object: Object to data processing for certain purposes.\n\nTo exercise these rights, contact us at privacy@homify.com.np. We will respond within 30 days."],
            ['9. Data Retention', "We retain your data for as long as necessary:\n\n• Active Accounts: Data is retained while your account is active.\n\n• Closed Accounts: Most data is deleted within 90 days of account closure.\n\n• Legal Requirements: Some data may be retained longer to comply with legal obligations (financial records: 7 years, dispute records: 3 years).\n\n• Anonymized Data: We may retain anonymized data indefinitely for analytics and research.\n\nYou can request account deletion anytime through Settings > Account > Delete Account."],
            ['10. Children\'s Privacy', "Homify Services is not intended for users under 18 years old. We do not knowingly collect personal information from children. If you believe we have inadvertently collected data from a child, please contact us immediately at privacy@homify.com.np, and we will delete it promptly."],
            ['11. Cookies & Tracking', "Our app uses:\n\n• Essential Cookies: Required for core functionality (authentication, security).\n\n• Analytics Cookies: To understand app usage and improve performance (Google Analytics, Firebase).\n\n• Advertising Cookies: To show relevant ads (with your consent).\n\nYou can manage cookie preferences in Settings > Privacy > Cookies. Disabling some cookies may affect app functionality."],
            ['12. Third-Party Services', "We integrate with third-party services:\n\n• Payment Gateways: Khalti, eSewa, IME Pay (their privacy policies apply)\n• Mapping Services: Google Maps (location data shared with Google)\n• Analytics: Firebase, Google Analytics\n• Cloud Storage: Secure cloud providers for data storage\n\nThese services have their own privacy policies. We recommend reviewing them. We are not responsible for third-party privacy practices."],
            ['13. International Data Transfers', "While Homify Services operates in Nepal, some data may be transferred to servers in other countries for:\n\n• Cloud storage and backup\n• Analytics and performance monitoring\n• Customer support tools\n\nWhen transferring data internationally, we ensure adequate protection through:\n• Standard contractual clauses\n• Data processing agreements\n• Compliance with applicable data protection laws"],
            ['14. Policy Changes', "We may update this Privacy Policy periodically to reflect:\n\n• Changes in our data practices\n• New features or services\n• Legal or regulatory requirements\n\nWe will notify you of significant changes through:\n• In-app notifications\n• Email notifications\n• Notice on our website\n\nThe 'Last Updated' date at the top indicates the latest revision. Continued use after changes constitutes acceptance of the updated policy."],
            ['15. Contact Us', "For privacy-related questions, concerns, or requests, contact us:\n\n• Email: privacy@homify.com.np\n• Phone: +977-1-4123456\n• Address: Homify Services Pvt. Ltd., Putalisadak, Kathmandu 44600, Nepal\n\nOur Data Protection Officer is available Monday-Friday, 9 AM - 6 PM NPT.\n\nWe take your privacy concerns seriously and will respond to all inquiries within 30 days."],
        ];
    @endphp

    <div class="container legal-body">
        <article class="legal-intro">
            <h2>Your privacy matters to us</h2>
            <p>We are committed to protecting your personal information and being transparent about how we use your data. This Privacy Policy explains our data practices in detail. We use industry-leading security measures and never sell your personal information to third parties.</p>
        </article>

        @foreach ($sections as [$title, $body])
            <article>
                <h2>{{ $title }}</h2>
                {!! $renderBody($body) !!}
            </article>
        @endforeach
    </div>
@endsection
