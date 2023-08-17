<?php

namespace JoelMelon\Plugins\LNURLAuth\Plugin;

/**
 * Assets
 *
 * Assets for this plugin are inline @v1.0.0 to simplify the dev workflow.
 *
 * @author Joel Stüdle <joel.stuedle@gmail.com>
 * @since 1.0.0
 */

// https://www.php.net/manual/en/class.allowdynamicproperties.php
#[\AllowDynamicProperties]

class Assets {

	/**
	 * Function to enqueue scripts and styles in frontend with minimal minification
	 *
	 * @since    1.0.0
	 */
	public function lnurl_auth_enqueue_scripts_styles() {
		$js = lnurl_auth()->Plugin->Helpers->minimize_javascript( $this->lnurl_auth_javascript() );
		wp_deregister_script( 'lnurl-auth' ); // prevent from double enqueuing
		wp_register_script( 'lnurl-auth', '', array(), md5( $js ), true );
		wp_enqueue_script( 'lnurl-auth' );
		wp_add_inline_script( 'lnurl-auth', $js );

		$css = lnurl_auth()->Plugin->Helpers->minimize_css( $this->lnurl_auth_css() );
		wp_deregister_style( 'lnurl-auth' ); // prevent from double enqueuing
		wp_register_style( 'lnurl-auth', '', array(), md5( $css ) );
		wp_enqueue_style( 'lnurl-auth' );
		wp_add_inline_style( 'lnurl-auth', $css );
	}

	/**
	 * Function to enqueue scripts and styles in admin with minimal minification
	 *
	 * @since    1.0.0
	 */
	public function lnurl_auth_enqueue_admin_scripts_styles() {
		$css = lnurl_auth()->Plugin->Helpers->minimize_css( $this->lnurl_auth_admin_css() );
		wp_deregister_style( 'lnurl-auth' ); // prevent from double enqueuing
		wp_register_style( 'lnurl-auth', '', array(), md5( $css ) );
		wp_enqueue_style( 'lnurl-auth' );
		wp_add_inline_style( 'lnurl-auth', $css );
	}

	/**
	 * Frontend javascript
	 *
	 * @since    1.0.0
	 */
	public function lnurl_auth_javascript() {
		return '
		const lnurlAuthElementClass = "lnurl-auth";
		// observe elements visibility
		const intersectionObserver = new IntersectionObserver((observers) => {
			observers.map(function(observer) {
				// If intersectionRatio is 0, the target is out of view
				if (observer.intersectionRatio <= 0) {
					observer.target.classList.remove("visible");
				} else {
					observer.target.classList.add("visible");
					observer.target.lnurlAuthRequest();
				}
			})
		});

		// function to check if element is in viewport
		Element.prototype.lnurlIsInViewPort = function() {
			if (this.classList.contains("visible")) {
				return true;
			};
			return false;
		};

		// function to reset lnurl element
		Element.prototype.lnurlAuthReset = function() {
			let qrcode = this.querySelector("." + lnurlAuthElementClass + "-qrcode");
			let permalink = this.querySelector("." + lnurlAuthElementClass + "-permalink");
			let clock = this.querySelector("." + lnurlAuthElementClass + "-timer-clock");
			let minutes = this.querySelector("." + lnurlAuthElementClass + "-timer-minutes");
			let seconds = this.querySelector("." + lnurlAuthElementClass + "-timer-seconds");
			let message = this.querySelector("." + lnurlAuthElementClass + "-message");
			qrcode.innerHTML = "";
			permalink.innerHTML = "";
			minutes.innerText = "5";
			seconds.innerText = "00";
			clock.innerText = "🕛";
			message.innerHTML = "";
			this.classList.remove(`⚡️`);
			this.classList.remove(`notification`);
			this.lnurlAuthRequest();
		};

		// function to cycle up until background found
		Element.prototype.lnurlGetRealBackgroundColor = function() {
			const element = this;
			const transparent = "rgba(0, 0, 0, 0)";
			if (!element) return transparent;

			var bg = getComputedStyle(element).backgroundColor;
			if (bg === "transparent" || bg === "rgba(0, 0, 0, 0)" && null !== element.parentElement) {
					return element.parentElement.lnurlGetRealBackgroundColor();
			} else {
					return bg;
			}
		};

		// function to cycle up until color found
		Element.prototype.lnurlGetRealColor = function() {
			const element = this;
			const transparent = "rgba(0, 0, 0, 0)";
			if (!element) return transparent;

			var color = getComputedStyle(element).color;
			if (color === "transparent" || color === "rgba(0, 0, 0, 0)" && null !== element.parentElement) {
					return element.parentElement.lnurlGetRealColor();
			} else {
					return color;
			}
		};

		// function to show a message
		Element.prototype.lnurlAuthMessage = function(message, countdown) {
			if (!message) return;
			clearInterval(countdown);

			const element = this;
			let message_element = this.querySelector("." + lnurlAuthElementClass + "-message");
			message_element.innerHTML = message;
			element.classList.add(`notification`);
		};

		Element.prototype.lnurlAuthRequest = function() {
			const element = this;

			if (!element.lnurlIsInViewPort() || element.classList.contains(`⚡️`)) {
				// console.log(element, element.lnurlIsInViewPort(), `abort`);
				return;
			}

			const data = new FormData();
			data.append( "action", "js_initialize_lnurl_auth" );
			data.append( "qrcode_width", Math.round(element.querySelector("." + lnurlAuthElementClass + "-qrcode").getBoundingClientRect().width ) ? Math.round(element.querySelector("." + lnurlAuthElementClass + "-qrcode").getBoundingClientRect().width ) : element.getBoundingClientRect().width );
			data.append( "foreground", element.dataset.foreground ? element.dataset.foreground : element.lnurlGetRealColor() );
			if (element.dataset.background) { data.append( "background", element.dataset.background ); }

			element.classList.add(`⚡️`);
			// console.log("Request from:", element);

			fetch("' . admin_url( 'admin-ajax.php' ) . '", {
				method: "POST",
				credentials: "same-origin",
				body: data
			})
			.then( res => res.json() )
			.then( response => {
				// console.log("Response for:", element, response, element.querySelector("." + lnurlAuthElementClass + "-qrcode"));

				if (`Success` !== response.status) {
					element.lnurlAuthMessage(response.message);
				}

				if (`Success` === response.status) {
					let qrcode = element.querySelector("." + lnurlAuthElementClass + "-qrcode");
					let permalink = element.querySelector("." + lnurlAuthElementClass + "-permalink");

					// insert response data
					qrcode.innerHTML = response.html.qrcode;
					permalink.innerHTML = response.html.permalink;

					// start countdown
					let init_countdown = new Date;
					let countdown = setInterval(function() {
						// check if authenticated
						const data = new FormData();
						data.append( "action", "js_await_lnurl_auth" );
						data.append( "k1", response.k1 );

						fetch("' . admin_url( 'admin-ajax.php' ) . '", {
							method: "POST",
							credentials: "same-origin",
							body: data
						})
						.then( res => res.json() )
						.then( response => {
							if (response.status === `Error` || response.status === `Timedout`) {
								element.lnurlAuthMessage(response.reason, countdown);
							}
							if (response.status === `Waiting`) {
								// console.log("Await authentication:", element);
							}
							if (response.status === `Signed`) {
								const urlSearchParams = new URLSearchParams(window.location.search);
								const params = Object.fromEntries(urlSearchParams.entries());
								if (!!params.redirect_to) { window.location.href = params.redirect_to; return; }
								if (element.dataset.redirect) { window.location.href = element.dataset.redirect; return; }
								window.location.href = response.redirect; return;
							}
						} )
						.catch( error => element.lnurlAuthMessage(error, countdown) );

						// update countdown
						let seconds_passed = Math.round((new Date - init_countdown) / 1000);
						let seconds_remaining = 300 - seconds_passed;
						let minutes_remaining = Math.floor(seconds_remaining / 60);
						let mseconds_remaining = seconds_remaining - minutes_remaining * 60;

						let clock = element.querySelector("." + lnurlAuthElementClass + "-timer-clock");
						let clock_img = element.querySelector("." + lnurlAuthElementClass + "-timer-clock img");
						let minutes = element.querySelector("." + lnurlAuthElementClass + "-timer-minutes");
						let seconds = element.querySelector("." + lnurlAuthElementClass + "-timer-seconds");

						let clock_icon = "🕛";
						if (seconds_remaining < 240) clock_icon = "🕒";
						if (seconds_remaining < 180) clock_icon = "🕧";
						if (seconds_remaining < 120) clock_icon = "🕘";
						if (seconds_remaining < 60) clock_icon = "🕙";

						if (minutes.innerText != minutes_remaining) minutes.innerText = minutes_remaining;
						if (seconds.innerText != mseconds_remaining) seconds.innerText = (String(mseconds_remaining).length <= 1 ? "0" + mseconds_remaining : mseconds_remaining);
						if (
							clock_img && clock_img.alt != clock_icon ||
							!clock_img && clock.innerText == clock_icon
						) {
							clock.innerText = clock_icon;
						}

						if (!seconds_remaining) {
							element.lnurlAuthMessage("' . _x( 'Authentication timed out.', 'lnurl_auth_javascript message', 'lnurl-auth' ) . '", countdown);
						};
					}, 1000);
				}
			} )
			.catch( error => console.log(error) );
		};

		window.onload = function() {
			const lnurlAuthInstances = document.getElementsByClassName(lnurlAuthElementClass);
			for (let lnurlAuthInstance of lnurlAuthInstances) {
				intersectionObserver.observe(lnurlAuthInstance);
				lnurlAuthInstance.lnurlAuthRequest();
				lnurlAuthInstance.querySelector("." + lnurlAuthElementClass + "-reinit").addEventListener("click", function() {
					lnurlAuthInstance.lnurlAuthReset();
				});
			}
		};
		';
	}

	/**
	 * Frontend styles
	 *
	 * @since    1.0.0
	 */
	public function lnurl_auth_css() {
		return '
		.lnurl-auth,
		.lnurl-auth-qrcode,
		.lnurl-auth-permalink { position: relative; width: 100%; }

		.lnurl-auth { display: flex; flex-wrap: wrap; color: inherit; background-color: inherit; }

		@keyframes lnurl_auth_loading_shimmer {
			0% { background-position: -100vw 0 }
			100% {background-position: 100vw 0}
		}
		.lnurl-auth-qrcode:empty,
		.lnurl-auth-permalink:empty {
			animation-duration: 2s;
			animation-fill-mode: forwards;
			animation-iteration-count: infinite;
			animation-name: lnurl_auth_loading_shimmer;
			animation-timing-function: linear;
			background: #F6F6F6;
			background: linear-gradient(to right, #F5F5F5 8%, #F0F0F0 18%, #F5F5F5 33%);
			background-size: 100vw 100vh;
			opacity: .2;
		}
		.lnurl-auth-qrcode:empty + .lnurl-auth-qrcode-logo { opacity: .2; }

		.lnurl-auth-label { flex: 0 0 100%; margin-bottom: 16px !important; color: inherit; text-align: center; }
		.lnurl-auth-qrcode-wrapper { position: relative; width: 100%; padding-top: 100%; flex: 0 0 100%; margin-bottom: 16px; overflow: hidden; }
		.lnurl-auth-qrcode { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
		.lnurl-auth-qrcode img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
		.lnurl-auth-qrcode-logo { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 28%; }
		.lnurl-auth-permalink { min-height: 1.5em; min-width: 10px; flex: 1; margin: 0; text-align: left; display: flex; align-items: center; justify-content: flex-start; }
		.lnurl-auth-permalink a { display: inline-block; }
		.lnurl-auth[data-foreground] .lnurl-auth-permalink a { color: inherit; }
		.lnurl-auth[data-foreground] .lnurl-auth-permalink a:hover,
		.lnurl-auth[data-foreground] .lnurl-auth-permalink a:active,
		.lnurl-auth[data-foreground] .lnurl-auth-permalink a:visited { color: inherit; opacity: .9; }
		.lnurl-auth-timer { flex: 0 0 5em; text-align: right; margin: 0; min-height: 1.5em; display: flex; align-items: center; justify-content: flex-end; }
		.lnurl-auth-timer-clock { position: relative; margin-top: .15em; margin-right: .25em; }
		.lnurl-auth-timer-minutes, .lnurl-auth-timer-seconds, .lnurl-auth-timer-separator { color: inherit; font-size: .9em; opacity: .4; }

		.lnurl-auth-message-wrapper { display: none; justify-content: center; align-items: center; position: absolute; left: 0; top: 0; width: 100%; height: 100%; border: 1px solid; text-align: center; }
		.lnurl-auth-message-scroll-wrapper { padding: 2em; max-height: calc(100% - 4em); overflow: scroll; }
		.lnurl-auth-reinit { margin-top: 1.25em; background: inherit; appearance: none; border: 1px solid; padding: 0.3em 0.5em; color: inherit; cursor: pointer; }

		.lnurl-auth.notification .lnurl-auth-qrcode,
		.lnurl-auth.notification .lnurl-auth-qrcode-logo,
		.lnurl-auth.notification .lnurl-auth-permalink,
		.lnurl-auth.notification .lnurl-auth-timer { opacity: 0; }
		.lnurl-auth.notification .lnurl-auth-label {margin-top: 16px; }

		.lnurl-auth.notification .lnurl-auth-message-wrapper { display: flex; }

		#loginform { display: flex; flex-direction: column; padding-bottom: 26px; }
		#loginform>p:first-child { order: 2; }
		#loginform .user-pass-wrap { order: 3; }
		#loginform .forgetmenot { order: 4; }
		#loginform .submit { order: 5; }
		#loginform .lnurl-auth-loginform { order: 1; text-align: center; }
		#loginform .lnurl-auth-loginform-wordpress-button { float: none; }
		#loginform .lnurl-auth-loginform-lightning-button { float: none; background: #F7931A; border-color: #EF8F1A; width: 100%; }
		#loginform .lnurl-auth-loginform-lightning-button:hover,
		#loginform .lnurl-auth-loginform-lightning-button:active { background: #EF8F1A; }
		#loginform .lnurl-auth-loginform-hr { border: 1px solid #e8e8e8; border-width: 1px 0px 0px 0px; appearance: none; margin: 30px 0 26px; }
		#loginform .lnurl-auth-loginform-divider { position: relative; }
		#loginform .lnurl-auth-loginform-divider-label { position: absolute; top: -0.9em; left: 50%; padding: 0.2em 1em; text-align: center; background-color: #ffffff; transform: translateX(-50%); text-transform: uppercase; }
		#loginform .lnurl-auth-qrcode-logo { color: #F7931A }
		#loginform .lnurl-auth-permalink a { color: #F7931A }
		#loginform .lnurl-auth-permalink a:hover, #loginform .lnurl-auth-permalink a:active, #loginform .lnurl-auth-permalink a:visited, { color: #EF8F1A }

		body:not(.⚡️) #loginform .lnurl-auth { display: none; }
		body:not(.⚡️) #loginform .lnurl-auth-loginform-wordpress-button { display: none; }

		body.⚡️ #loginform .lnurl-auth-loginform-lightning-button { display: none; }
		body.⚡️ #loginform .lnurl-auth-loginform-wordpress-button { display: inline-block; }
		body.⚡️ #loginform > p:first-child {display: none; }
		body.⚡️ #loginform .user-pass-wrap {display: none; }
		body.⚡️ #loginform .forgetmenot {display: none; }
		body.⚡️ #loginform .submit {display: none; }

		body.lnurl-auth-wordpress-only #loginform .lnurl-auth-loginform { display: none; }
		body.lnurl-auth-lightning-only #loginform .lnurl-auth-loginform-divider,
		body.lnurl-auth-lightning-only #loginform .lnurl-auth-loginform-wordpress-button { display: none; }
		';
	}

	/**
	 * Admin styles
	 *
	 * @since    1.0.0
	 */
	public function lnurl_auth_admin_css() {
		return '
		@media (min-width: 500px) {
			.lnurl-auth-admin-donate-grid { display: grid; grid-gap: 20px; grid-template-columns: 45% 55%; }
		}
		@media (min-width: 1200px) {
			.lnurl-auth-admin-columns { display: grid; grid-gap: 20px; grid-template-columns: 1fr 350px; }
			.lnurl-auth-admin-donate-grid { display: block; }
		}

		.lnurl-auth-admin-columns .card { max-width: none; }
		.lnurl-auth-settings-group label,
		.lnurl-auth-settings-group input[type="text"],
		.lnurl-auth-settings-group input[type="url"],
		.lnurl-auth-settings-group select,
		.lnurl-auth-settings-group textarea { width: 100%; max-width: none; }
		.lnurl-auth-settings-group-legend { font-style: italic; margin-bottom: 12px; padding-top: 5px; }
		.lnurl-auth-admin-shortcode > h5 { margin-bottom: 6px; }
		.lnurl-auth-admin-shortcode > h4 + * { margin-top: -6px; }
		.lnurl-auth-admin-donate > h5 + * { margin-top: -6px; }
		#lnurl-auth-usercreation-roles { height: 7.55em; padding-top: .5em; }
		.lnurl-auth-admin-donate-qrcode { position: relative; }
		.lnurl-auth-admin-donate-qrcode span { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 50px; }
		';
	}
}
