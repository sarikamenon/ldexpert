<?php

it('redirects landing to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
