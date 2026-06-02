<?php
class ForbiddenException extends ApiException {
    public function __construct(string $m = 'Acces interzis') { parent::__construct($m, 403); }
}
