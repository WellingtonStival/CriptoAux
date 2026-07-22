<?php

return [
    /*
     * Valida se o dominio do email informado no cadastro tem registro DNS
     * de verdade (MX/A/AAAA), bloqueando dominios inventados (ex:
     * "usuario@bol" sem TLD). Desligado por padrao nos testes (ver
     * phpunit.xml) porque dominios de teste como "example.com" tem um
     * registro "Null MX" de proposito (RFC 7505 - sinaliza "nao aceito
     * email"), o que faria a checagem de DNS falhar mesmo sendo um
     * dominio real.
     */
    'validate_email_dns' => env('VALIDATE_EMAIL_DNS', true),
];
