<?php

// Do not allow inserts to be enabled on the frontend on wpcom
add_filter( 'wp_redirect_manager_allow_insert', '__return_false', 9999 );
