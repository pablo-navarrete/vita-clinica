(() => {
    function syncConditions(form) {
        const value = name => form.querySelector('input[type="checkbox"][name="' + name + '"]')?.checked;
        const set = (name, disabled, required) => {
            const field=form.querySelector('[name="'+name+'"]');
            if(field) { field.disabled=disabled; field.required=required; }
        };
        for(const period of ['month','year']) for(const key of ['amount','additional_branch_amount']) {
            const enabled=value('definition[prices]['+period+'][enabled]');
            set('definition[prices]['+period+']['+key+']',!enabled,Boolean(enabled));
        }
        for(const code of ['users','professionals','branches']) {
            const unlimited=value('definition[limits]['+code+'][unlimited]');
            set('definition[limits]['+code+'][value]',Boolean(unlimited),!unlimited);
        }
        for(const metric of ['ai_tokens','storage_bytes']) {
            const prefix='definition[quotas]['+metric+']';
            const enabled=value(prefix+'[enabled]'), unlimited=value(prefix+'[unlimited]');
            for(const field of form.querySelectorAll('[name^="'+prefix+'["]')) {
                if(field.name===prefix+'[enabled]') continue;
                field.disabled=!enabled || (field.name===prefix+'[quantity]' && unlimited);
                field.required=Boolean(enabled) && ['[quantity]','[reset_period]','[overage_policy]'].some(key=>field.name===prefix+key) && !(field.name===prefix+'[quantity]' && unlimited);
            }
        }
    }

    document.querySelectorAll('[data-plan-form]').forEach(form => {
        syncConditions(form);
        form.addEventListener('change', () => syncConditions(form));
    });
})();
