(() => {
    const money = value => '$' + value.toLocaleString('es-CL') + ' CLP';
    document.querySelectorAll('[data-net-price]').forEach(box => {
        const target = box.querySelector('[data-net-target]');
        const rateInput = box.querySelector('[data-net-rate]');
        const vatInput = box.querySelector('[data-net-vat]');
        const result = box.querySelector('[data-net-result]');
        const form = box.closest('form');
        const price = form.querySelector(`[name="definition[prices][${box.dataset.period}][amount]"]`);
        let rate, vatRate, fixed;
        const breakdown = gross => {
            const fee = (gross * rate + 500000n) / 1000000n + fixed;
            const vat = (fee * vatRate + 500000n) / 1000000n;
            return { fee, vat, net: gross - fee - vat };
        };
        const calculate = () => {
            const percentage = (input, fallback) => {
                const value = input ? input.value : fallback;
                return value !== '' && /^\d+(\.\d{1,4})?$/.test(value ?? '') ? Math.round(Number(value) * (input ? 10000 : 1)) : NaN;
            };
            const rateValue = percentage(rateInput, box.dataset.rate);
            const vatValue = percentage(vatInput, box.dataset.vat);
            if (!Number.isSafeInteger(rateValue) || !Number.isSafeInteger(vatValue) || rateValue < 0 || rateValue > 990000 || vatValue < 0 || vatValue > 1000000) {
                result.textContent = 'Ingresa una comisión y un IVA válidos para calcular el precio.'; return;
            }
            rate = BigInt(rateValue); vatRate = BigInt(vatValue); fixed = BigInt(box.dataset.fixed || '0');
            const desired = Number(target.value);
            if (target.value === '' || !Number.isSafeInteger(desired) || desired < 0 || desired > 1000000000000) {
                result.textContent = 'Ingresa un monto válido en pesos enteros.'; return;
            }
            // Conservative ceiling before CLP rounding; advance until the desired net is met.
            const denominator = 1000000000000n - rate * (1000000n + vatRate);
            if (denominator <= 0n) { result.textContent = 'La tarifa configurada no permite calcular este precio.'; return; }
            const numerator = BigInt(desired) * 1000000000000n + fixed * (1000000n + vatRate) * 1000000n;
            let gross = (numerator + denominator - 1n) / denominator;
            while (gross <= 1000000000000n && breakdown(gross).net < BigInt(desired)) gross++;
            if (gross > 1000000000000n) { result.textContent = 'El precio calculado supera el importe permitido.'; return; }
            const values = breakdown(gross);
            price.value = String(gross);
            price.dispatchEvent(new Event('input', { bubbles: true }));
            result.textContent = `Comisión Flow: ${Number(rate) / 10000}% + ${money(Number(fixed))}\nIVA sobre la comisión: ${Number(vatRate) / 10000}%\nPrecio a cobrar: ${money(Number(gross))}\nComisión: ${money(Number(values.fee))}\nIVA de la comisión: ${money(Number(values.vat))}\nRecibirías: ${money(Number(values.net))}`;
        };
        target.addEventListener('input', calculate);
        rateInput?.addEventListener('input', calculate);
        vatInput?.addEventListener('input', calculate);
        const saveButton = box.querySelector('[data-net-save]');
        saveButton?.addEventListener('click', async () => {
            const status = box.querySelector('[data-net-save-status]');
            if (!rateInput.value || !vatInput.value || !rateInput.checkValidity() || !vatInput.checkValidity()) {
                status.textContent = 'Ingresa porcentajes válidos antes de guardar.'; return;
            }
            saveButton.disabled = true;
            status.textContent = 'Guardando porcentajes…';
            try {
                const response = await fetch(box.dataset.saveUrl, {
                    method: 'PUT',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                    body: JSON.stringify({ rate_percent: rateInput.value, vat_percent: vatInput.value }),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'No se pudieron guardar los porcentajes.');
                document.querySelectorAll('[data-net-price]').forEach(other => {
                    other.querySelector('[data-net-rate]').value = rateInput.value;
                    other.querySelector('[data-net-vat]').value = vatInput.value;
                });
                status.textContent = data.message;
            } catch (error) {
                status.textContent = error.message || 'No se pudieron guardar los porcentajes.';
            } finally { saveButton.disabled = false; }
        });
        price.addEventListener('input', () => {
            if (document.activeElement === price) {
                target.value = '';
                result.textContent = 'Precio ajustado manualmente. Ingresa el neto deseado para volver a calcular.';
            }
        });
    });
    document.querySelectorAll('[data-fee-estimator]').forEach(box => {
        if (!box.dataset.rate) return;
        const form = box.closest('form');
        const get = name => form.querySelector(`[name="${name}"]`);
        function update() {
            const extra = Number(box.querySelector('[data-fee-extra]').value);
            for (const period of ['month', 'year']) {
                const prefix = `definition[prices][${period}]`;
                const enabled = form.querySelector(`input[type="checkbox"][name="${prefix}[enabled]"]`)?.checked;
                const base = get(prefix + '[amount]'), additional = get(prefix + '[additional_branch_amount]');
                const output = box.querySelector(`[data-fee-output="${period}"]`);
                const gross = Number(base?.value) + extra * Number(additional?.value);
                if (!enabled || base?.value === '' || additional?.value === '' || !Number.isSafeInteger(extra) || extra < 0 || extra > 1000000 || !Number.isSafeInteger(gross) || gross < 0 || gross > 1000000000000) {
                    output.textContent = 'Activa el período e ingresa importes y sedes válidos.'; continue;
                }
                // Integer arithmetic matches the backend, including half-up rounding in CLP.
                const fee = (BigInt(gross) * BigInt(box.dataset.rate) + 500000n) / 1000000n + BigInt(box.dataset.fixed);
                const vat = (fee * BigInt(box.dataset.vat) + 500000n) / 1000000n;
                output.textContent = `Precio al cliente: ${money(gross)}\nComisión: ${money(Number(fee))}\nIVA de la comisión: ${money(Number(vat))}\nDescuento total: ${money(Number(fee + vat))}\nRecibirías: ${money(gross - Number(fee + vat))}`;
            }
        }
        form.addEventListener('input', update); form.addEventListener('change', update); update();
    });
})();
