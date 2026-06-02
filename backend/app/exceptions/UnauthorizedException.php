<?php
class UnauthorizedException extends ApiException {
    public function __construct(string $m = 'Neautentificat') { parent::__construct($m, 401); }
}
