@if (config('backpack.base.setup_two_factor_auth', false))
<script>
(function () {
    const COOKIE_NAME = @json(\App\Support\TwoFactorDevice::COOKIE_NAME);
    const COOKIE_MAX_AGE_DAYS = @json(\App\Support\TwoFactorDevice::trustDays());
    const INPUT_ID = 'two_factor_device_id';

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, maxAgeDays) {
        const maxAge = maxAgeDays * 24 * 60 * 60;
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
    }

    function generateUuid() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function applyDeviceId() {
        const input = document.getElementById(INPUT_ID);
        if (!input) {
            return;
        }

        let deviceId = input.value || getCookie(COOKIE_NAME);
        if (!deviceId) {
            deviceId = generateUuid();
        }

        input.value = deviceId;
        setCookie(COOKIE_NAME, deviceId, COOKIE_MAX_AGE_DAYS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyDeviceId);
    } else {
        applyDeviceId();
    }
})();
</script>
@endif
