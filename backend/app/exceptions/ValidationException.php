<?php
class ValidationException extends ApiException {
    public function __construct(string $m) { parent::__construct($m, 400); }
}
