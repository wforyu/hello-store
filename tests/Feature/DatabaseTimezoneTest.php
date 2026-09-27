<?php

namespace Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

class DatabaseTimezoneTest extends TestCase
{
    /**
     * Raw query di model memakai NOW() (Slider::scopeActive, Coupon::isValid,
     * FlashSale, Notification, OrderDownload, SalesTarget, SocialFollowClaim),
     * sedangkan kolom start_at/end_at ditulis Eloquent dari PHP yang timezone-nya
     * Asia/Jakarta. Kalau session MySQL ikut default host (InfinityFree = UTC),
     * NOW() meleset 7 jam dan banner/coupon/flash sale mati lebih awal.
     */
    public function test_koneksi_mysql_memaksa_session_timezone_wib(): void
    {
        $this->assertSame('+07:00', config('database.connections.mysql.timezone'));
        $this->assertSame('+07:00', config('database.connections.mariadb.timezone'));
    }

    /**
     * Named zone ('Asia/Jakarta') butuh tabel mysql.time_zone yang tidak dimuat
     * di shared hosting, jadi PDO akan gagal saat connect. Offset selalu diterima
     * karena dihitung aritmetis.
     */
    public function test_memakai_offset_bukan_named_timezone(): void
    {
        $timezone = config('database.connections.mysql.timezone');

        $this->assertMatchesRegularExpression('/^[+-]\d{2}:\d{2}$/', $timezone);
    }

    /**
     * Indonesia abolisi DST (2008), jadi WIB = +07:00 sepanjang tahun dan cocok
     * dengan offset yang dipaksa. Kalau app timezone diubah, test ini gagal dan
     * menunjukkan offset DB ikut harus diubah.
     */
    public function test_offset_database_cocok_dengan_app_timezone(): void
    {
        $appOffset = (new DateTimeZone(config('app.timezone')))
            ->getOffset(new DateTimeImmutable('2026-01-15 00:00:00'));

        $dbOffset = ((int) config('database.connections.mysql.timezone')) * 3600;

        $this->assertSame(
            $appOffset,
            $dbOffset,
            'Offset session MySQL harus sama dengan app timezone, kalau tidak NOW() dan now() beda.'
        );
    }

    public function test_app_timezone_adalah_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }
}
