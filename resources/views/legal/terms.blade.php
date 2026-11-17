@extends('layouts.site')

@section('title', 'Terms of Service — Homify Services')
@section('description', 'The terms and conditions that apply when you use the Homify platform, bookings and payments in Nepal.')
@section('canonical', config('app.public_url') . '/terms')

@section('content')
    <div class="legal-hero">
        <div class="container">
            <h1>Terms of Service</h1>
            <p>Legal agreement &middot; Last updated January 2025</p>
        </div>
    </div>

    @php
        $sections = [
            ['1. Acceptance of Terms', 'By accessing and using Homify Services ("the Platform"), you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our services. These terms constitute a legally binding agreement between you and Homify Services.'],
            ['2. Service Description', "Homify Services is a comprehensive marketplace platform connecting customers with verified service providers for various home and professional services across Nepal. We facilitate bookings, payments, and communication but do not directly provide the services ourselves. Our platform ensures quality through rigorous partner vetting and customer feedback systems."],
            ['3. User Account & Responsibilities', "Users must be at least 18 years old and provide accurate, current information during registration. You are responsible for maintaining account security, including password confidentiality. All activities under your account are your responsibility. Notify us immediately of any unauthorized access. You agree to use the platform lawfully and not engage in fraudulent activities."],
            ['4. Service Provider Responsibilities', "Service partners must be qualified professionals with necessary licenses, certifications, and insurance. Partners must maintain professional standards, provide quality services, honor booking commitments, and comply with safety regulations. Partners are independent contractors, not employees of Homify Services, and are responsible for their own taxes and legal obligations."],
            ['5. Payments, Fees & Pricing', "All prices are displayed in Nepali Rupees (NPR). Payment terms, service fees, and cancellation policies are outlined at booking. We use secure payment processing with multiple payment options. Service fees may apply to platform usage. Refunds are processed according to our cancellation policy. Partners receive payments after successful service completion and quality verification."],
            ['6. Bookings & Cancellations', "Bookings are confirmed upon partner acceptance and payment. Cancellation policies vary by service type and are displayed before booking. Early cancellations may receive full or partial refunds. Late cancellations may incur fees. No-shows may result in full charges. Rescheduling is subject to partner availability. Force majeure events may warrant special consideration."],
            ['7. Quality Assurance & Standards', "We maintain quality through partner verification, customer ratings, and regular performance reviews. All service providers undergo background checks and skill verification. We reserve the right to suspend or terminate partners who fail to meet quality standards. Customer satisfaction is our priority, and we actively monitor service quality."],
            ['8. Privacy & Data Protection', "Your privacy is important to us. We collect, use, and protect personal information in accordance with our Privacy Policy and Nepal's data protection laws. We implement industry-standard security measures to safeguard your data. We do not sell personal information to third parties. Please review our Privacy Policy for detailed information."],
            ['9. Limitation of Liability', "Homify Services acts as an intermediary platform. While we verify partners, we are not liable for the quality, safety, timeliness, or legality of services provided by partners. We do not guarantee uninterrupted platform availability. Our total liability is limited to the amount paid for the specific service. We are not responsible for indirect, incidental, or consequential damages."],
            ['10. User Conduct & Prohibited Activities', "Users must not engage in harassment, discrimination, or abusive behavior toward partners or other users. Prohibited activities include: fraudulent bookings, false reviews, unauthorized commercial use, system hacking, spamming, and violation of intellectual property rights. Violations may result in account suspension or termination and legal action."],
            ['11. Reviews & Ratings', "Customers may rate and review services after completion. Reviews must be honest, relevant, and non-defamatory. We reserve the right to remove reviews that violate our policies. Fake or manipulated reviews are strictly prohibited. Partners cannot incentivize positive reviews or penalize negative feedback."],
            ['12. Dispute Resolution', "We encourage resolution through our customer support team first. If disputes cannot be resolved amicably, they will be handled through mediation or arbitration in accordance with Nepal's laws. The jurisdiction shall be Kathmandu, Nepal. Legal proceedings must be initiated within one year of the dispute arising."],
            ['13. Intellectual Property Rights', "All content, trademarks, logos, and intellectual property on Homify Services are owned by us or our licensors. Users may not reproduce, distribute, modify, or create derivative works without written permission. User-generated content (reviews, photos) remains user property but grants us a license to use for platform purposes."],
            ['14. Modifications & Updates', "We reserve the right to modify these Terms of Service at any time. Users will be notified of significant changes via email or in-app notification. Continued use after notification constitutes acceptance of modified terms. We may update platform features, pricing, or policies to improve user experience."],
            ['15. Termination of Service', "We may suspend or terminate accounts for violation of these terms, fraudulent activity, or misuse of the platform. Users may terminate their accounts at any time through account settings. Upon termination, pending bookings must be completed or cancelled according to policy. Certain provisions survive termination, including payment obligations and liability limitations."],
            ['16. Governing Law & Jurisdiction', "These Terms are governed by the laws of Nepal. Any legal disputes shall be resolved in the courts of Kathmandu, Nepal. If any provision is found invalid, the remaining provisions remain in effect. These terms constitute the entire agreement between you and Homify Services regarding platform use."],
        ];
    @endphp

    <div class="container legal-body">
        <article class="legal-intro">
            <h2>Welcome to Homify Services</h2>
            <p>Please read these Terms of Service carefully before using our platform. By accessing or using Homify Services, you agree to be bound by these terms and conditions.</p>
        </article>

        @foreach ($sections as [$title, $body])
            <article>
                <h2>{{ $title }}</h2>
                <p>{{ $body }}</p>
            </article>
        @endforeach
    </div>
@endsection
