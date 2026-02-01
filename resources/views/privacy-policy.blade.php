<x-app-layout :title="'Privacy Policy'">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Privacy Policy
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto p-6">
        <p>Effective Date: January 23, 2026</p>

        <p>At <strong>CleanMyData</strong>, we value your privacy and are committed to protecting your personal information. This Privacy Policy explains how we collect, use, and safeguard the data of users who interact with our web application for cleaning and managing datasets.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">1. Information We Collect</h2>
        <ul class="list-disc ml-6">
            <li><strong>User Information:</strong> When you register, we collect your name, email, and password (securely hashed).</li>
            <li><strong>Dataset Information:</strong> When you upload datasets, we store metadata such as dataset name, upload date, columns, and rows. The actual data is used solely for processing and cleaning purposes.</li>
            <li><strong>Usage Data:</strong> We may collect anonymous information about how you use the application to improve functionality and user experience.</li>
        </ul>

        <h2 class="text-2xl font-semibold mt-6 mb-2">2. How We Use Your Information</h2>
        <ul class="list-disc ml-6">
            <li>To provide and maintain our data cleaning services.</li>
            <li>To process and clean datasets as requested by the user.</li>
            <li>To communicate with users regarding updates, security, and support.</li>
        </ul>

        <h2 class="text-2xl font-semibold mt-6 mb-2">3. Data Sharing</h2>
        <p>We do not sell or share your personal data or datasets with third parties. Data may be shared only with authorized personnel for the purpose of maintaining and improving the service.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">4. Data Security</h2>
        <p>We implement industry-standard security measures to protect your information. All user passwords are securely hashed, and sensitive data is stored in a secure PostgreSQL database.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">5. User Rights</h2>
        <p>You have the right to access, correct, or delete your personal information. You may contact us to exercise these rights.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">6. Changes to This Privacy Policy</h2>
        <p>We may update this Privacy Policy from time to time. Users will be notified of significant changes through the application.</p>

        <h2 class="text-2xl font-semibold mt-6 mb-2">Contact Us</h2>
        <p>If you have questions about this Privacy Policy, you can contact us at <strong>support@cleanmydata.com</strong>.</p>
    </div>
</x-app-layout>
