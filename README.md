# Info
hCaptcha for Elementor Forms

I was tired of Google's ReCAPTCHA failing to prevent spam on my WP / Elementor sites so I hacked this together.

# Known Bugs

1. This plugin uses a custom form element called 'hcaptcha', elementor pro hard codes some options based on element type to enable/disable options such as "Required", so the "Required" option must be toggled off when added to a form.

2. This plugin does not support the themes or sizes that ReCAPTCHA supports, again, these options are hard coded to the field type in Elementor.

3. There's an error that pops up if you have WP_DEBUG enabled, I haven't bothered to look into it. If you know how to fix it please let me know.

4. Probably some other stuff, I hacked this together in like 30 minutes.
