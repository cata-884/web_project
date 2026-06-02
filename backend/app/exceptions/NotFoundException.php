<?php
class NotFoundException extends ApiException {
    public function __construct(string $m = 'Resursă inexistentă') { parent::__construct($m, 404); }
}
