<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneTicket Fintech | Laravel Reference</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .scale-up { animation: scaleUp 0.4s ease-out forwards; }
        @keyframes scaleUp { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 p-4 md:p-8 text-slate-900">

    <!-- 1. VERIFICATION OVERLAY (Triggered on Redirect Return) -->
    <div id="verify-overlay" class="hidden fixed inset-0 bg-slate-900/90 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="bg-white p-12 rounded-[3rem] shadow-2xl text-center max-w-sm w-full scale-up">
            <div id="verify-icon" class="text-indigo-600 text-6xl mb-6 flex justify-center">
                 <i data-lucide="loader-2" class="w-16 h-16 animate-spin"></i>
            </div>
            <h2 id="verify-title" class="text-2xl font-black text-slate-800">Syncing Payment</h2>
            <p id="verify-msg" class="text-slate-500 mt-2">Laravel is verifying your transaction with OneKhusa...</p>
        </div>
    </div>

    <div id="app" class="max-w-4xl mx-auto">
        <!-- Header -->
        <header class="flex flex-col md:flex-row justify-between items-center mb-12">
            <h1 class="text-3xl font-black text-blue-700 uppercase tracking-tighter">
                ONETICKET <span class="text-slate-300 font-light italic text-xl">FINTECH</span>
            </h1>
            <div class="flex items-center gap-3 mt-4 md:mt-0">
                <a href="/rtp" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 border border-indigo-200 px-3 py-1 rounded-full">REQUEST TO PAY (TAN)</a>
                <div class="bg-indigo-100 text-indigo-700 px-4 py-1 rounded-full text-xs font-bold flex items-center gap-2 border border-indigo-200">
                    <span class="w-2 h-2 bg-indigo-600 rounded-full animate-pulse"></span> LARAVEL BLADE
                </div>
            </div>
        </header>

        <main>
            <!-- Success Screen -->
            <div id="view-success" class="hidden bg-white p-16 rounded-[3rem] border shadow-2xl text-center scale-up">
                <i data-lucide="party-popper" class="mx-auto text-emerald-500 mb-6 w-24 h-24"></i>
                <h2 class="text-4xl font-black mb-4">Payment Verified!</h2>
                <p class="mb-8 text-slate-500 text-lg">Your transaction was successful via the Laravel integration.</p>
                <button onclick="location.href='/'" class="bg-blue-600 text-white px-12 py-4 rounded-[2rem] font-black uppercase tracking-widest hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                    Buy Another Ticket
                </button>
            </div>

            <!-- Failure Screen (redirected here when payment fails) -->
            <div id="view-failure" class="hidden bg-white p-16 rounded-[3rem] border shadow-2xl text-center scale-up">
                <i data-lucide="x-circle" class="mx-auto text-red-500 mb-6 w-24 h-24"></i>
                <h2 class="text-4xl font-black mb-4">Payment Not Completed</h2>
                <p id="failure-msg" class="mb-8 text-slate-500 text-lg">Your transaction was not successful. Please try again.</p>
                <button onclick="location.href='/'" class="bg-slate-900 text-white px-12 py-4 rounded-[2rem] font-black uppercase tracking-widest hover:bg-slate-700 transition shadow-lg">
                    Try Again
                </button>
            </div>

            <!-- Purchase View -->
            <div id="view-purchase" class="max-w-xl mx-auto space-y-6">
                <div class="bg-white p-10 rounded-[3rem] border shadow-sm text-center">
                    <i data-lucide="ticket" class="mx-auto text-indigo-600 mb-4 opacity-20 w-16 h-16"></i>
                    <h2 class="text-2xl font-bold mb-2 text-slate-400">Laravel Showcase Entry</h2>
                    <p class="text-5xl font-black mb-10 text-slate-800">MWK 2,500</p>
                    
                    <button id="buyBtn" onclick="handleBuy()" class="w-full bg-slate-900 text-white py-6 rounded-[2rem] font-black text-xl flex justify-center items-center gap-3 hover:bg-indigo-700 transition disabled:opacity-50 shadow-xl">
                        <i data-lucide="credit-card"></i>
                        <span id="buyBtnText">PURCHASE WITH LARAVEL</span>
                    </button>
                </div>

            </div>
        </main>

        <footer class="mt-20 text-center text-slate-400 text-[10px] uppercase tracking-[0.2em] font-bold">
            OneKhusa PHP Laravel Reference &copy; 2026
        </footer>
    </div>

    <script>
        // Detection Logic for Redirect landing
        const urlParams = new URLSearchParams(window.location.search);
        const refFromUrl = urlParams.get('ref');
        const failedFromUrl = urlParams.get('failed');

        if (failedFromUrl) {
            // The customer was redirected back via the failureRedirectionUrl.
            showFailure('The payment was not completed on the checkout page.');
        } else if (refFromUrl) {
            document.getElementById('verify-overlay').classList.remove('hidden');
            lucide.createIcons();
            checkPaymentStatus(refFromUrl);
        }

        /**
         * Step 1: Initiate Checkout via Laravel API
         */
        async function handleBuy() {
            const btn = document.getElementById('buyBtn');
            const btnText = document.getElementById('buyBtnText');
            btn.disabled = true;
            btnText.innerText = "REDIRECTING...";

            try {
                // Calls Route::post('/api/Tickets/buy/{eventId}')
        
                const response = await fetch('/api/Tickets/buy/showcase', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Redirect to OneKhusa Hosted Page
                    window.location.href = result.redirectUrl;
                } else {
                    alert("Laravel Error: " + result.message);
                    resetButton();
                }
            } catch (error) {
                alert("Connection failed. Ensure php artisan serve is running.");
                resetButton();
            }
        }

        /**
         * Step 2: Verification Logic (Polling)
         * Since Laravel is stateless, we check the status endpoint
         */
        async function checkPaymentStatus(ref) {
            try {
                // Calls Route::get('/api/Tickets/status/{reference}')
                const res = await fetch(`/api/Tickets/status/${ref}`);
                const data = await res.json();

                if (data.status === "Paid") {
                    showFinalSuccess();
                } else if (data.status === "Failed") {
                    showFailure('This payment failed. Please try again.');
                } else {
                    // Pending / NotFound - keep polling every 3 seconds
                    setTimeout(() => checkPaymentStatus(ref), 3000);
                }
            } catch (e) {
                setTimeout(() => checkPaymentStatus(ref), 5000);
            }
        }

        function showFailure(message) {
            const icon = document.getElementById('verify-icon');
            if (icon) {
                icon.classList.remove('animate-spin');
                icon.innerHTML = '<i data-lucide="x-circle" class="w-16 h-16 text-red-500"></i>';
                document.getElementById('verify-title').innerText = "Payment Failed";
                document.getElementById('verify-msg').innerText = message;
                lucide.createIcons();
                setTimeout(() => {
                    document.getElementById('verify-overlay').classList.add('hidden');
                }, 2500);
            }
            document.getElementById('failure-msg').innerText = message;
            document.getElementById('view-purchase').classList.add('hidden');
            document.getElementById('view-failure').classList.remove('hidden');
            window.history.replaceState({}, document.title, "/");
        }

        function showFinalSuccess() {
            const icon = document.getElementById('verify-icon');
            icon.classList.remove('animate-spin');
            icon.innerHTML = '<i data-lucide="check-circle" class="w-16 h-16 text-emerald-500"></i>';
            document.getElementById('verify-title').innerText = "Payment Confirmed!";
            document.getElementById('verify-msg').innerText = "OneKhusa verified successfully.";
            lucide.createIcons();

            setTimeout(() => {
                document.getElementById('verify-overlay').classList.add('hidden');
                document.getElementById('view-purchase').classList.add('hidden');
                document.getElementById('view-success').classList.remove('hidden');
                // Clean the browser URL bar
                window.history.replaceState({}, document.title, "/");
            }, 2500);
        }

        function resetButton() {
            const btn = document.getElementById('buyBtn');
            const btnText = document.getElementById('buyBtnText');
            btn.disabled = false;
            btnText.innerText = "PURCHASE WITH LARAVEL";
        }

        lucide.createIcons();
    </script>
</body>
</html>