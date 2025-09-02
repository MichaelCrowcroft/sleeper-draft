{{-- Privacy Policy Page --}}
<x-layouts.app.marketing title="Privacy Policy - SleeperDraft">
    <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] px-4 sm:px-6 py-8 sm:py-12">
        <div class="mx-auto max-w-4xl">
            <header class="mb-12 text-center">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white mb-6 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path fill-rule="evenodd" d="M15 10a1 1 0 01-1 1H8.414L10.707 13.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 111.414 1.414L8.414 9H14a1 1 0 011 1z" clip-rule="evenodd" />
                    </svg>
                    Back to Home
                </a>
                <h1 class="text-4xl font-bold tracking-tight sm:text-5xl mb-4">
                    Privacy Policy
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    Last updated: {{ date('F j, Y') }}
                </p>
            </header>

            <div class="prose prose-gray prose-lg dark:prose-invert max-w-none">
                <div class="space-y-8">
                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Introduction</h2>
                        <p class="leading-relaxed">
                            This Privacy Policy describes how SleeperDraft ("we," "our," or "us") collects, uses, and shares your personal information when you use our fantasy football draft assistance service.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Information We Collect</h2>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-xl font-medium mb-2">Account Information</h3>
                                <p class="leading-relaxed">
                                    When you create an account, we collect your name, email address, and other information you provide during registration.
                                </p>
                            </div>
                            <div>
                                <h3 class="text-xl font-medium mb-2">Draft and League Data</h3>
                                <p class="leading-relaxed">
                                    We access and store draft information from your Sleeper fantasy football leagues to provide our draft assistance services. This includes player data, draft picks, and league settings.
                                </p>
                            </div>
                            <div>
                                <h3 class="text-xl font-medium mb-2">Usage Information</h3>
                                <p class="leading-relaxed">
                                    We automatically collect information about your use of our service, including log data, device information, and usage patterns.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">How We Use Your Information</h2>
                        <ul class="space-y-2 list-disc list-inside">
                            <li>To provide and improve our draft assistance services</li>
                            <li>To personalize your experience and provide relevant recommendations</li>
                            <li>To communicate with you about our services</li>
                            <li>To ensure the security and integrity of our platform</li>
                            <li>To comply with legal obligations</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Information Sharing</h2>
                        <p class="leading-relaxed">
                            We do not sell, rent, or share your personal information with third parties except in the following circumstances:
                        </p>
                        <ul class="mt-4 space-y-2 list-disc list-inside">
                            <li>With your explicit consent</li>
                            <li>To comply with legal requirements</li>
                            <li>To protect our rights and the safety of our users</li>
                            <li>With service providers who assist in operating our platform (under strict confidentiality agreements)</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Data Security</h2>
                        <p class="leading-relaxed">
                            We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the internet or electronic storage is 100% secure.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Your Rights</h2>
                        <p class="leading-relaxed">
                            You have the right to:
                        </p>
                        <ul class="mt-4 space-y-2 list-disc list-inside">
                            <li>Access and review your personal information</li>
                            <li>Update or correct your personal information</li>
                            <li>Delete your account and associated data</li>
                            <li>Opt out of certain communications</li>
                            <li>Data portability (receive your data in a structured format)</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Cookies and Tracking</h2>
                        <p class="leading-relaxed">
                            We use cookies and similar technologies to enhance your browsing experience, analyze site usage, and personalize content. You can control cookie settings through your browser preferences.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Third-Party Services</h2>
                        <p class="leading-relaxed">
                            Our service integrates with Sleeper Fantasy Football to provide draft assistance. Please review Sleeper's privacy policy to understand how they handle your data. We are not responsible for the privacy practices of third-party services.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Children's Privacy</h2>
                        <p class="leading-relaxed">
                            Our service is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If you are a parent or guardian and believe your child has provided us with personal information, please contact us.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Changes to This Policy</h2>
                        <p class="leading-relaxed">
                            We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date. We encourage you to review this Privacy Policy periodically.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-semibold mb-4">Contact Us</h2>
                        <p class="leading-relaxed">
                            If you have any questions about this Privacy Policy or our privacy practices, please contact us at:
                        </p>
                        <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <p class="font-medium">Email: privacy@sleeperdraft.com</p>
                            <p class="font-medium">GitHub: <a href="https://github.com/michaelcrowcroft/sleeperdraft" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">github.com/michaelcrowcroft/sleeperdraft</a></p>
                        </div>
                    </section>
                </div>
            </div>

            <footer class="mt-16 pt-8 border-t border-gray-200 dark:border-gray-700 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    © {{ date('Y') }} SleeperDraft. All rights reserved.
                </p>
            </footer>
        </div>
    </div>
</x-layouts.app.marketing>
