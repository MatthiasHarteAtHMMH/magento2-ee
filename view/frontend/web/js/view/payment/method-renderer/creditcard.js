/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License Version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

/* globals WPP */

define(
    [
        "jquery",
        "Wirecard_ElasticEngine/js/view/payment/method-renderer/default",
        "mage/translate",
        "mage/url",
        "Magento_Vault/js/view/payment/vault-enabler"
    ],
    function ($, Component, $t, url, VaultEnabler) {
        "use strict";
        return Component.extend({
            defaults: {
                template: "Wirecard_ElasticEngine/payment/method-creditcard"
            },

            getPaymentPageScript: function () {
                return window.checkoutConfig.payment[this.getCode()].wpp_url;
            },

            seamlessFormInitVaultEnabler: function () {
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
            },

            seamlessFormInit: function () {
                let uiInitData = {"txtype": this.getCode()};
                let wrappingDivId = this.getCode() + "_seamless_form";
                let formSizeHandler = this.seamlessFormSizeHandler.bind(this);
                let formInitHandler = this.seamlessFormInitErrorHandler.bind(this);
                let messageContainer = this.messageContainer;

                // Build seamless renderform with full transaction data
                $.ajax({
                    url: url.build("wirecard_elasticengine/frontend/creditcard"),
                    type: "post",
                    data: uiInitData,
                    success: function (result) {
                        if ("OK" === result.status) {
                            let uiInitData = JSON.parse(result.uiData);
                            WPP.seamlessRender({
                                requestData: uiInitData,
                                wrappingDivId: wrappingDivId,
                                onSuccess: formSizeHandler,
                                onError: formInitHandler
                            });
                        } else {
                            messageContainer.addErrorMessage({message: $t("credit_card_form_loading_error")});
                        }
                    },
                    error: function (err) {
                        messageContainer.addErrorMessage({message: $t("credit_card_form_loading_error")});
                        console.error("Error : " + JSON.stringify(err));
                    }
                });
            },
            seamlessFormSubmitSuccessHandler: function (response) {
                if (response.hasOwnProperty("acs_url")) {
                    this.redirectCreditCard(response);
                } else {
                    // Handle redirect for Non-3D transactions
                    $.ajax({
                        url: url.build("wirecard_elasticengine/frontend/redirect"),
                        type: "post",
                        data: {
                            "data": response,
                            "method": "creditcard"
                        }
                    }).done(function (redirect) {
                        //exchange this with proper magento function at later point
                        document.open();
                        document.write(redirect);
                        document.close();
                    });
                }
            },
            /**
             * Handle 3Ds credit card transactions within callback
             * @param response
             */
            redirectCreditCard: function (response) {
                let result = {};
                result.data = {};
                let appendFormData = this.appendFormData.bind(this);
                $.ajax({
                    url: url.build("wirecard_elasticengine/frontend/callback"),
                    type: "post",
                    data: {"jsresponse": response},
                    success: function (result) {
                        if (result.data["form-url"]) {
                            let form = $("<form />", {
                                action: result.data["form-url"],
                                method: result.data["form-method"]
                            });
                            appendFormData(result.data, form);
                            form.appendTo("body").submit();
                        }
                    },
                    error: function (err) {
                        this.messageContainer.addErrorMessage({message: $t("credit_card_form_loading_error")});
                        console.error("Error : " + JSON.stringify(err));
                    }
                });
            },
            appendFormData: function (data, form) {
                for (let key in data) {
                    if (key !== "form-url" && key !== "form-method") {
                        form.append($("<input />", {
                            type: "hidden",
                            name: key,
                            value: data[key]
                        }));
                    }
                }
            },
            seamlessFormInitErrorHandler: function (response) {
                this.messageContainer.addErrorMessage({message: $t("credit_card_form_loading_error")});
                console.error(response);
            },
            seamlessFormSubmitErrorHandler: function (response) {
                this.messageContainer.addErrorMessage({message: $t("credit_card_form_submitting_error")});
                console.error(response);

                setTimeout(function () {
                    location.reload();
                }, 3000);
            },
            seamlessFormSizeHandler: function () {
                window.addEventListener("resize", this.resizeIFrame.bind(this));
                let seamlessForm = document.getElementById(this.getCode() + "_seamless_form");
                if (seamlessForm !== null) {
                    this.resizeIFrame(seamlessForm);
                }
            },
            resizeIFrame: function (seamlessForm) {
                let iframe = seamlessForm.firstElementChild;
                if (iframe) {
                    if (iframe.clientWidth > 768) {
                        iframe.style.height = "267px";
                    } else if (iframe.clientWidth > 460) {
                        iframe.style.height = "341px";
                    } else {
                        iframe.style.height = "415px";
                    }
                }
            },

            getData: function () {
                return {
                    "method": this.getCode(),
                    "po_number": null,
                    "additional_data": {
                        "is_active_payment_token_enabler": this.vaultEnabler.isActivePaymentTokenEnabler()
                    }
                };
            },
            selectPaymentMethod: function () {
                this._super();

                return true;
            },

            /**
             * Submit credit card request
             */
            afterPlaceOrder: function () {
                WPP.seamlessSubmit({
                    wrappingDivId: this.getCode() + "_seamless_form",
                    onSuccess: this.seamlessFormSubmitSuccessHandler.bind(this),
                    onError: this.seamlessFormSubmitErrorHandler.bind(this)
                });
            },

            /**
             * @returns {String}
             */
            getVaultCode: function () {
                return window.checkoutConfig.payment[this.getCode()].vaultCode;
            },

            /**
             * @returns {Bool}
             */
            isVaultEnabled: function () {
                return this.vaultEnabler.isVaultEnabled();
            }
        });
    }
);
