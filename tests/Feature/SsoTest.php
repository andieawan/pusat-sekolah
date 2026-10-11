<?php

namespace Tests\Feature;

use App\Models\SsoToken;

class SsoTest extends PakaiData
{
    private function ambilKode($response): string
    {
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('xyz', $q['state']);

        return $q['code'];
    }

    public function test_alur_lengkap_login_tunggal(): void
    {
        [$app, $secret] = $this->buatAplikasi();
        $user = $this->buatUser([], $app, ['guru']);

        $res = $this->actingAs($user)->get('/sso/authorize?'.http_build_query([
            'client_id' => $app->client_id, 'redirect_uri' => self::REDIRECT, 'response_type' => 'code', 'state' => 'xyz',
        ]));
        $res->assertRedirect();
        $this->assertStringStartsWith(self::REDIRECT, $res->headers->get('Location'));
        $kode = $this->ambilKode($res);

        $tukar = $this->postJson('/api/sso/token', [
            'grant_type' => 'authorization_code', 'code' => $kode, 'redirect_uri' => self::REDIRECT,
        ], $this->basic($app->client_id, $secret));
        $tukar->assertOk()->assertJsonPath('user.username', $user->username)->assertJsonPath('user.peran', ['guru']);

        $info = $this->getJson('/api/sso/userinfo', ['Authorization' => 'Bearer '.$tukar->json('access_token')]);
        $info->assertOk()->assertJsonPath('sub', $user->uuid);
    }

    public function test_kode_hanya_bisa_dipakai_sekali(): void
    {
        [$app, $secret] = $this->buatAplikasi();
        $user = $this->buatUser([], $app, ['guru']);
        $kode = $this->ambilKode($this->actingAs($user)->get('/sso/authorize?'.http_build_query([
            'client_id' => $app->client_id, 'redirect_uri' => self::REDIRECT, 'response_type' => 'code', 'state' => 'xyz',
        ])));
        $badan = ['grant_type' => 'authorization_code', 'code' => $kode, 'redirect_uri' => self::REDIRECT];

        $this->postJson('/api/sso/token', $badan, $this->basic($app->client_id, $secret))->assertOk();
        $this->postJson('/api/sso/token', $badan, $this->basic($app->client_id, $secret))->assertStatus(400);
    }

    public function test_redirect_uri_tidak_terdaftar_ditolak_tanpa_redirect(): void
    {
        [$app] = $this->buatAplikasi();
        $user = $this->buatUser([], $app, ['guru']);

        $this->actingAs($user)->get('/sso/authorize?'.http_build_query([
            'client_id' => $app->client_id, 'redirect_uri' => 'https://jahat.example/x', 'response_type' => 'code', 'state' => 'xyz',
        ]))->assertStatus(400);
    }

    public function test_akun_tanpa_peran_di_aplikasi_ditolak(): void
    {
        [$app] = $this->buatAplikasi();
        $user = $this->buatUser();

        $this->actingAs($user)->get('/sso/authorize?'.http_build_query([
            'client_id' => $app->client_id, 'redirect_uri' => self::REDIRECT, 'response_type' => 'code', 'state' => 'xyz',
        ]))->assertStatus(403);
    }

    public function test_belum_login_diarahkan_ke_halaman_login(): void
    {
        [$app] = $this->buatAplikasi();

        $this->get('/sso/authorize?'.http_build_query([
            'client_id' => $app->client_id, 'redirect_uri' => self::REDIRECT, 'response_type' => 'code', 'state' => 'xyz',
        ]))->assertRedirect(route('login'));
    }

    public function test_secret_salah_ditolak(): void
    {
        [$app] = $this->buatAplikasi();

        $this->postJson('/api/sso/token', ['grant_type' => 'authorization_code', 'code' => 'x', 'redirect_uri' => self::REDIRECT],
            $this->basic($app->client_id, 'salah'))->assertStatus(401);
    }

    public function test_pkce_wajib_cocok_bila_dipakai(): void
    {
        [$app, $secret] = $this->buatAplikasi();
        $user = $this->buatUser([], $app, ['guru']);
        $verifier = str_repeat('a', 64);
        $tantangan = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $kode = $this->ambilKode($this->actingAs($user)->get('/sso/authorize?'.http_build_query([
            'client_id' => $app->client_id, 'redirect_uri' => self::REDIRECT, 'response_type' => 'code', 'state' => 'xyz',
            'code_challenge' => $tantangan, 'code_challenge_method' => 'S256',
        ])));

        $this->postJson('/api/sso/token', [
            'grant_type' => 'authorization_code', 'code' => $kode, 'redirect_uri' => self::REDIRECT, 'code_verifier' => str_repeat('b', 64),
        ], $this->basic($app->client_id, $secret))->assertStatus(400);
    }

    public function test_menonaktifkan_akun_mencabut_token(): void
    {
        [$app, $secret] = $this->buatAplikasi();
        $user = $this->buatUser([], $app, ['guru']);
        $kode = $this->ambilKode($this->actingAs($user)->get('/sso/authorize?'.http_build_query([
            'client_id' => $app->client_id, 'redirect_uri' => self::REDIRECT, 'response_type' => 'code', 'state' => 'xyz',
        ])));
        $token = $this->postJson('/api/sso/token', [
            'grant_type' => 'authorization_code', 'code' => $kode, 'redirect_uri' => self::REDIRECT,
        ], $this->basic($app->client_id, $secret))->json('access_token');

        $user->update(['aktif' => false]);

        $this->assertSame(0, SsoToken::whereNull('dicabut_pada')->count());
        $this->getJson('/api/sso/userinfo', ['Authorization' => 'Bearer '.$token])->assertStatus(401);
    }
}
