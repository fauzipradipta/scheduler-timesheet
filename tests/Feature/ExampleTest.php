<?php

test('the root url sends visitors to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
