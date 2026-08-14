<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request To Pay | OneKhusa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 p-4 md:p-8 text-slate-900">

    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <header class="flex flex-col md:flex-row justify-between items-center mb-10">
            <h1 class="text-3xl font-black text-blue-700 uppercase tracking-tighter">
                ONETICKET <span class="text-slate-300 font-light italic text-xl">FINTECH</span>
            </h1>
            <a href="/" class="text-sm font-bold text-indigo-600 hover:text-indigo-800">&larr; Back to Checkout</a>
        </header>

        <main class="space-y-8">

            <!-- Step explanation -->
            <div class="bg-white p-6 rounded-[2rem] border shadow-sm">
                <h2 class="text-lg font-black mb-3 flex items-center gap-2">
                    <i data-lucide="info" class="text-blue-500"></i> HOW REQUEST TO PAY WORKS
                </h2>
                <ol class="list-decimal list-inside text-sm text-slate-600 space-y-1">
                    <li>Your system generates a <b>unique reference number</b> to associate with the collection transaction.</li>
                    <li>We call the OneKhusa <code class="bg-slate-100 px-1 rounded">/collections/requestToPay/initiate</code> endpoint to generate a <b>Timed Account Number (TAN)</b> that expires in <b>15 minutes</b>.</li>
                    <li>The customer pays using the TAN and the transaction amount via their bank or MNO.</li>
                    <li>Once credited, OneKhusa triggers the <code class="bg-slate-100 px-1 rounded">payrequest.success</code> webhook.</li>
                    <li>Your system notifies the customer that the payment was successful.</li>
                </ol>
            </div>

            <!-- Initiate form -->
            <div class="bg-white p-8 rounded-[3rem] border shadow-sm space-y-5">
                <h2 class="text-2xl font-black">Generate TAN</h2>

                <div>
                    <label class="text-xs font-bold uppercase tracking-widest text-slate-400">Reference Number (5-25 alphanumeric)</label>
                    <div class="flex gap-2 mt-1">
                        <input id="reference" type="text" maxlength="25" placeholder="e.g. 1020XDFS76GS777"
                               class="flex-1 px-4 py-3 rounded-2xl border border-slate-200 focus:border-indigo-400 focus:outline-none" />
                        <button type="button" onclick="generateRef()"
                                class="px-4 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-sm font-bold text-slate-600">Generate</button>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold uppercase tracking-widest text-slate-400">Transaction Amount</label>
                    <input id="amount" type="number" step="0.01" min="0.01" placeholder="e.g. 2500.00"
                           class="mt-1 w-full px-4 py-3 rounded-2xl border border-slate-200 focus:border-indigo-400 focus:outline-none" />
                </div>

                <div>
                    <label class="text-xs font-bold uppercase tracking-widest text-slate-400">Transaction Description</label>
                    <input id="description" type="text" placeholder="e.g. Samsung 85inch TV purchase"
                           class="mt-1 w-full px-4 py-3 rounded-2xl border border-slate-200 focus:border-indigo-400 focus:outline-none" />
                </div>

                <div>
                    <label class="text-xs font-bold uppercase tracking-widest text-slate-400">Captured By (email) <span class="normal-case text-slate-300">- optional, defaults to .env</span></label>
                    <input id="capturedBy" type="email" placeholder="username@example.com"
                           class="mt-1 w-full px-4 py-3 rounded-2xl border border-slate-200 focus:border-indigo-400 focus:outline-none" />
                </div>

                <button id="initBtn" onclick="initiateRtp()"
                        class="w-full bg-slate-900 text-white py-4 rounded-[2rem] font-black uppercase tracking-widest hover:bg-indigo-700 transition disabled:opacity-50 shadow-lg">
                    INITIATE REQUEST TO PAY
                </button>
            </div>

            <!-- Result / TAN display -->
            <div id="result" class="hidden bg-white p-8 rounded-[3rem] border shadow-sm space-y-5">
                <div class="flex items-center gap-3">
                    <i data-lucide="landmark" class="text-blue-600 w-8 h-8"></i>
                    <div>
                        <h2 class="text-xl font-black">Timed Account Number (TAN)</h2>
                        <p class="text-sm text-slate-500">Share this with your customer. It expires in <b id="expiry-minutes">15</b> minutes.</p>
                    </div>
                </div>

                <div class="bg-blue-50 border-2 border-dashed border-blue-300 rounded-3xl p-6 text-center">
                    <div id="tan" class="text-4xl font-black tracking-widest text-blue-700 select-all">----------</div>
                    <div class="text-xs text-slate-400 mt-1">Reference: <span id="result-ref" class="font-bold text-slate-600 select-all"></span></div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-center">
                    <div class="bg-slate-50 rounded-2xl p-4">
                        <div class="text-xs uppercase font-bold text-slate-400">Expires in</div>
                        <div id="countdown" class="text-2xl font-black text-slate-800">15:00</div>
                    </div>
                    <div class="bg-slate-50 rounded-2xl p-4">
                        <div class="text-xs uppercase font-bold text-slate-400">Status</div>
                        <div id="status" class="text-2xl font-black text-amber-500">Pending</div>
                    </div>
                </div>

                <p class="text-sm text-slate-500">
                    The customer pays the amount using the TAN above. Once credited, OneKhusa sends the
                    <code class="bg-slate-100 px-1 rounded">payrequest.success</code> webhook and this page will update automatically.
                </p>
            </div>

        </main>

        <footer class="mt-20 text-center text-slate-400 text-[10px] uppercase tracking-[0.2em] font-bold">
            OneKhusa Request To Pay &copy; 2026
        </footer>
    </div>

    <script>
        lucide.createIcons();

        let pollTimer = null;

        function generateRef() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
            let ref = '';
            for (let i = 0; i < 15; i++) ref += chars[Math.floor(Math.random() * chars.length)];
            document.getElementById('reference').value = ref;
        }

        function setResultText(id, text, color) {
            const el = document.getElementById(id);
            el.textContent = text;
            el.className = 'text-2xl font-black ' + (color || 'text-slate-800');
        }

        async function initiateRtp() {
            const btn = document.getElementById('initBtn');
            const ref = document.getElementById('reference').value.trim();
            const amount = document.getElementById('amount').value;
            const description = document.getElementById('description').value.trim();
            const capturedBy = document.getElementById('capturedBy').value.trim();

            if (!ref || !amount || !description) {
                alert('Please fill in the reference number, amount and description.');
                return;
            }

            btn.disabled = true;
            btn.innerText = 'GENERATING TAN...';

            try {
                const res = await fetch('/api/requestToPay/initiate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ reference: ref, amount: amount, description: description, capturedBy: capturedBy })
                });
                const result = await res.json();

                if (result.status !== 'success') {
                    alert('Error: ' + (result.message || 'Unknown error'));
                    return;
                }

                // Show TAN + expiry
                document.getElementById('result').classList.remove('hidden');
                document.getElementById('tan').textContent = result.timedAccountNumber || 'N/A';
                document.getElementById('result-ref').textContent = result.reference;
                document.getElementById('expiry-minutes').textContent = result.expiryInMinutes;

                const expiresOn = new Date(result.expiryDate);
                startCountdown(expiresOn);
                startPolling(result.reference);
            } catch (e) {
                alert('Connection failed. Ensure php artisan serve is running.');
            } finally {
                btn.disabled = false;
                btn.innerText = 'INITIATE REQUEST TO PAY';
            }
        }

        function startCountdown(expiresOn) {
            const update = () => {
                const diff = Math.max(0, expiresOn.getTime() - Date.now());
                const m = Math.floor(diff / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                document.getElementById('countdown').textContent =
                    String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                if (diff > 0) setTimeout(update, 1000);
            };
            update();
        }

        function startPolling(ref) {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(async () => {
                try {
                    const res = await fetch(`/api/requestToPay/status/${ref}`);
                    const data = await res.json();
                    const statusEl = document.getElementById('status');

                    if (data.status === 'Paid') {
                        setResultText('status', 'Paid', 'text-emerald-500');
                        clearInterval(pollTimer);
                    } else if (data.status === 'Failed') {
                        setResultText('status', 'Failed', 'text-red-500');
                        clearInterval(pollTimer);
                    } else {
                        setResultText('status', 'Pending', 'text-amber-500');
                    }
                } catch (e) { /* keep polling */ }
            }, 3000);
        }
    </script>
</body>
</html>
