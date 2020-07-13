<?php
Configure::set('Virtualmin.email_templates', [
    'en_us' => [
        'lang' => 'en_us',
        'text' => 'Thank you for choosing us for your Virtualmin Hosting!

Here are the details for your server:

Virtualmin URL: https://{module.host_name}:{module.port}
Domain Name: {service.virtualmin_domain}
User Name: {service.virtualmin_username}
Password: {service.virtualmin_password}

Thank you for your business!',
        'html' => '<p>Thank you for choosing us for your Virtualmin Hosting!</p>
<p>Here are the details for your server:</p>
<p>Virtualmin URL: https://{module.host_name}:{module.port}<br />Domain Name: {service.virtualmin_domain}<br />User Name: {service.virtualmin_username}<br />Password: {service.virtualmin_password}</p>
<p>Thank you for your business!</p>'
    ]
]);
