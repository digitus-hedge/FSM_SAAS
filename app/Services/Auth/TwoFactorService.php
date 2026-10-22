<?php
namespace App\Services\Auth;

use App\Models\{PlatformUser, User};
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorService
{
    public function __construct(private Google2FA $g2fa) {}

    public function generateSecret(): string
    {
        return $this->g2fa->generateSecretKey();
    }

    public function qrCodeInline(string $issuer, string $accountEmail, string $secret): string
    {
        // Returns a <svg> string that you drop into the Blade.
        return $this->g2fa->getQRCodeInline($issuer, $accountEmail, $secret);
    }

    public function verify(string $secret, string $code): bool
{
    $this->g2fa->setWindow(20);   // ± 2 min drift tolerance
    return (bool) $this->g2fa->verifyKey($secret, $code);
}
    /**
     * Build the stored secret, write it to the user, return the plaintext for the setup QR.
     * The model's 'two_factor_secret' is encrypted via Eloquent cast.
     */
    public function startSetup(User|PlatformUser $user): string
    {
        $secret = $this->generateSecret();
        $user->update([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => null,
        ]);
        return $secret;
    }

    public function confirm(User|PlatformUser $user, string $code): bool
    {
        if (! $user->two_factor_secret || ! $this->verify($user->two_factor_secret, $code)) {
            return false;
        }
        $user->update(['two_factor_confirmed_at' => now()]);
        return true;
    }

    public function disable(User|PlatformUser $user): void
    {
        $user->update([
            'two_factor_secret'       => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

}