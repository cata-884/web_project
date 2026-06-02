<?php
class ConflictException extends ApiException {
    public function __construct(string $m) { parent::__construct($m, 409); }
}
