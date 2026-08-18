<?php

it('does not expose local data export', function (): void {
    $this->get('/settings/export')->assertNotFound();
});

it('does not accept local data import', function (): void {
    $this->post('/settings/import')->assertNotFound();
});
