<?php
declare(strict_types=1);
namespace Suite\Tenancy;

use RuntimeException;
use Suite\Database\Connection;
use Suite\Support\Audit;

/** Company-owned branding; binary files never enter the public filesystem. */
final class CompanySettings
{
    public function ready(): bool
    {
        return Connection::tableExists('company_settings');
    }

    public function forCompany(int $companyId): array
    {
        $default = ['contact_email' => '', 'brand_colour' => '#2563eb', 'has_logo' => false];
        if (!$this->ready()) return $default;
        $stmt = Connection::pdo()->prepare('SELECT contact_email, brand_colour, logo_mime FROM company_settings WHERE organization_id = ?');
        $stmt->execute([$companyId]);
        $row = $stmt->fetch();
        return $row ? ['contact_email' => (string) $row['contact_email'], 'brand_colour' => (string) $row['brand_colour'], 'has_logo' => !empty($row['logo_mime'])] : $default;
    }

    public function canView(array $user, int $companyId): bool
    {
        if (empty($user['active'])) return false;
        foreach (suite_companies()->managedBy($user) as $company) {
            if ((int) $company['id'] === $companyId) return true;
        }
        foreach (suite_projects()->forUser($user) as $project) {
            if ((int) $project['organization_id'] === $companyId) return true;
        }
        return false;
    }

    public function logoFor(array $user, int $companyId): ?array
    {
        if (!$this->canView($user, $companyId)) throw new RuntimeException('Company access denied.');
        if (!$this->ready()) return null;
        $stmt = Connection::pdo()->prepare('SELECT logo_mime, logo_base64 FROM company_settings WHERE organization_id = ?');
        $stmt->execute([$companyId]);
        $row = $stmt->fetch();
        if (!$row || !in_array($row['logo_mime'], ['image/png', 'image/jpeg'], true)) return null;
        $bytes = base64_decode((string) $row['logo_base64'], true);
        return $bytes === false ? null : ['mime' => $row['logo_mime'], 'bytes' => $bytes];
    }

    public function save(array $user, int $companyId, array $input, ?string $logoBytes = null): void
    {
        $company = suite_companies()->requireCompany($user, $companyId);
        if (empty($company['active'])) throw new RuntimeException('Activate the company before changing branding.');
        if (!$this->ready()) throw new RuntimeException('The platform owner must apply the company settings migration first.');
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['contact_email'] ?? '')));
        $colour = (string) ($input['brand_colour'] ?? '#2563eb');
        if ($name === '' || strlen($name) > 160) throw new RuntimeException('Enter a company name up to 160 characters.');
        if (strlen($email) > 190 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) throw new RuntimeException('Enter a valid contact email.');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $colour)) throw new RuntimeException('Choose a valid brand colour.');
        $mime = null;
        if ($logoBytes !== null) {
            if (strlen($logoBytes) > 262144) throw new RuntimeException('Use a PNG or JPEG logo up to 256 KB.');
            $image = @getimagesizefromstring($logoBytes);
            if (!$image || !in_array($image[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
                || $image[0] > 2000 || $image[1] > 2000 || $image[0] < 1 || $image[1] < 1) {
                throw new RuntimeException('Use a PNG or JPEG logo no larger than 2000 × 2000 pixels.');
            }
            $mime = $image[2] === IMAGETYPE_PNG ? 'image/png' : 'image/jpeg';
        }
        $pdo = Connection::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE organizations SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$name, $companyId]);
            $check = $pdo->prepare('SELECT organization_id FROM company_settings WHERE organization_id = ?');
            $check->execute([$companyId]);
            if (!$check->fetchColumn()) {
                $stmt = $pdo->prepare('INSERT INTO company_settings (organization_id, contact_email, brand_colour) VALUES (?, ?, ?)');
                $stmt->execute([$companyId, $email, strtolower($colour)]);
            } else {
                $stmt = $pdo->prepare('UPDATE company_settings SET contact_email = ?, brand_colour = ?, updated_at = CURRENT_TIMESTAMP WHERE organization_id = ?');
                $stmt->execute([$email, strtolower($colour), $companyId]);
            }
            if ($logoBytes !== null || !empty($input['remove_logo'])) {
                $stmt = $pdo->prepare('UPDATE company_settings SET logo_mime = ?, logo_base64 = ? WHERE organization_id = ?');
                $stmt->execute([$mime, $logoBytes === null ? null : base64_encode($logoBytes), $companyId]);
            }
            Audit::record('company.branding_saved', (int) $user['id'], $companyId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
