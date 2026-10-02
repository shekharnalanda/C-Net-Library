<?php

class PortalHelperBootstrapReached extends RuntimeException {}

class PortalHelperTestKernel
{
    public function bootstrap(): void
    {
        throw new PortalHelperBootstrapReached;
    }
}

class PortalHelperTestApplication
{
    public function make(string $class): PortalHelperTestKernel
    {
        if ($class !== 'Illuminate\\Contracts\\Console\\Kernel') {
            throw new RuntimeException('Wrong console kernel binding: '.$class);
        }

        return new PortalHelperTestKernel;
    }
}

$fixture = sys_get_temp_dir().'/portal-helper-'.bin2hex(random_bytes(8));
mkdir($fixture.'/vendor', 0700, true);
mkdir($fixture.'/bootstrap', 0700, true);
file_put_contents($fixture.'/vendor/autoload.php', '<?php');
file_put_contents($fixture.'/bootstrap/app.php', '<?php return new PortalHelperTestApplication;');
$failures = 0;
try {
    foreach (['preflight.php', 'backup.php', 'verify.php', 'repair-campus.php', 'mail-inspect.php', 'mail-probe.php', 'portal-check.php', 'retry-welcome.php'] as $helper) {
        $argv = [__DIR__.'/'.$helper, $fixture, 'library', 'owner@example.test'];
        try {
            require $argv[0];
            throw new RuntimeException('Expected bootstrap checkpoint was not reached.');
        } catch (PortalHelperBootstrapReached) {
            echo 'PASS | '.$helper." | actual PHP kernel binding\n";
        } catch (Throwable $e) {
            $failures++;
            fwrite(STDERR, 'FAIL | '.$helper.' | '.$e->getMessage()."\n");
        }
    }
} finally {
    unlink($fixture.'/vendor/autoload.php');
    unlink($fixture.'/bootstrap/app.php');
    rmdir($fixture.'/vendor');
    rmdir($fixture.'/bootstrap');
    rmdir($fixture);
}
exit($failures ? 1 : 0);
