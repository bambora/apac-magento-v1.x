function createOverlay() {
    $$("body")[0].scrollTo();
    element = '<div class="bambora-overlay" style="position: absolute; left: 0px; top: 0px; display: table-cell; text-align: center; vertical-align: middle; background: rgba(0, 0, 0, 0.75); z-index: 10000; height: 1695px; width: 100%;"><iframe id="bambora-iframe" src="' + BASE_URL + 'bambora/index/integratedcheckout" style="padding: 0px; border: none; z-index: 999999; background-color: rgb(255, 255, 255);background-repeat: no-repeat; background-position: 50% 50%; width: 400px; min-width: 320px; height: 704px; margin: 35px auto 0px; display: table-row; background-size: 25%; text-align: center; box-shadow: rgb(0, 0, 0) 0px 0px 70px 0px;"></iframe></div>';
    $$('body')[0].insert(element);
    $$(".bambora-overlay").each(function(element) {
        element.observe('click', function() {
            this.remove()
        });
    });    
    console.log("hello");
    return false ;
}

function removeOverlay() {
    $$(".bambora-overlay")[0].remove()
}
                
var BamboraCheckout = Class.create();
BamboraCheckout.prototype = {
    _extensions:[],
    _btn: null,
    _BamboraBtn: null,
    _BamboraBtnId : 'bambora-place-order',
    options: { 
        methodCode: "bambora_integrated"
    },
    initialize: function() {
    },
    setupBamboraPlaceOrderButton: function() {      
        var btnClone = this._btn.clone(true);
        var _this = this;
        btnClone.setAttribute('id', this._BamboraBtnId);
        this._btn.insert({before: btnClone});
        this._BamboraBtn = btnClone;
    },  
    switchButtons: function(hideAll) {
        var btnToShow = '';
        var submitEl = this._btn;
        var BamboraBtn = this._BamboraBtn;
        var buttons = [BamboraBtn, submitEl];

        btnToShow = BamboraBtn;
        
        buttons.each(function(elem) {
            if (elem) {
                if (elem == btnToShow) {
                    elem.show();
                } else {
                    elem.hide();
                }
            }
        });   
    },
    getCurrentExtension: function() {
        var $this = this;
        var extObj = null;

        this._extensions.each(function(extension) {
            if (extension.name.toLowerCase() == $this.options.checkoutExtension.toLowerCase()) {
                extObj = new extension.class;
            }
        });

        return extObj;
    },    
    setup:function(config) {
        var $this = this, ext, queryParams;

        try {
            this.options = Object.extend(this.options, config);    
            ext = $this.getCurrentExtension();

            if (ext != undefined) {
                $mainbambora = this;
                ext.setup(this);
                $$('input[name="payment[method]"]').each(function(element) {
                    element.observe('click', function() {
                        ext.changeMethod($mainbambora)
                    });
                });
            }
        } catch (e) {
            console.log(e);
        }
    }, 
    register: function(eClass, eName) {
        this._extensions.push({class:eClass, name:eName});
    },    
}

if ($$(".firecheckout-index-index")[0] != undefined) {
    var Bambora_Firecheckout_Checkout = Class.create();
    Bambora_Firecheckout_Checkout.prototype = {
        super: null,  
        _payment:null,
        _transport:null,
        _btn: null,
        initialize: function(superClass) {
        this.super = superClass;
        this._btn = $$('.btn-checkout')[0];
        },
        setup: function(superClass) {
            if (payment.currentMethod == "bambora_integrated") {
                this.super = superClass;
                var _this = this;    
                FireCheckout.prototype.save = FireCheckout.prototype.save.wrap(function(e) {    
                    var form = new VarienForm('firecheckout-form');
                    
                    if (form.validator.validate())  {
                        already_placing_order = true;
                        billing.save();
                        createOverlay();
                        //Event.stop(e);
                    }   

                    return false;
                });
            }
        },
        changeMethod: function(superClass) {
            if (payment.currentMethod ==  "bambora_integrated") {
                this.super = superClass;
                var _this = this;    
                FireCheckout.prototype.save = FireCheckout.prototype.save.wrap(function(e) {    
                    var form = new VarienForm('firecheckout-form');

                    if (form.validator.validate()) {
                        already_placing_order = true;
                        billing.save();
                        createOverlay();
                        //Event.stop(e);
                    }   

                    return false;
                });
            }          
        }
    }
}

if ($$(".onestepcheckout-index-index")[0] != undefined) {
    var Bambora_Idev_OnestepCheckout = Class.create();
    Bambora_Idev_OnestepCheckout.prototype = {
        super: null,
        initialize: function(superClass) {
        },
        setup: function(superClass) {    
            //if(payment.currentMethod ==  "bambora_integrated") {
                var $this = this;
                this.super = superClass;
                this.super._btn = $('onestepcheckout-place-order');
                this.super.setupBamboraPlaceOrderButton();
                this.super._BamboraBtn.observe('click',this.idevCheckout.bind(this));
                this.super.switchButtons();
            //}
            Ajax.Responders.register({
                onComplete: function(request, transport) {
                    // Avoid AJAX callback for internal AJAX request
                    if (typeof request.parameters.doNotMakeAjaxCallback == 'undefined') {   
                        if (payment.currentMethod !=  "bambora_integrated") {
                            $("bambora-place-order").hide()
                            $("onestepcheckout-place-order").show()
                        } else {
                            $("bambora-place-order").show()
                            $("onestepcheckout-place-order").hide()
                        }
                    }
                }
            });
           
        },
        idevCheckout:function(e) {
            var form = new VarienForm('onestepcheckout-form');
            
            if(form.validator.validate()) {
                //already_placing_order = true;
                createOverlay();
                get_save_billing_function(BASE_URL + 'onestepcheckout/ajax/save_billing/', BASE_URL + 'onestepcheckout/ajax/set_methods_separate/', true, true)();
                Event.stop(e);
            }
            
            
            return false;
        
        }, 
        changeMethod: function(superClass) {
            if (payment.currentMethod ==  "bambora_integrated") {
                var $this = this;
                this.super = superClass;
                this.super._btn = $('onestepcheckout-place-order');
                
                if ($("bambora-place-order") == undefined) {
                    this.super.setupBamboraPlaceOrderButton();
                }
                //this.super._BamboraBtn.observe('click',this.idevCheckout.bind(this));
                this.super.switchButtons();
            } else {
                this.super._BamboraBtn.unbind('click');
            } 
        }
    }
    
}

if ($$(".checkout-onepage-index")[0] != undefined) {
    var Bambora_Mage_Checkout = Class.create();

    Bambora_Mage_Checkout.prototype = {
        super: null,  
        _payment:null,
        _transport:null,
        _btn: null,
        initialize: function(superClass) {
        this.super = superClass;
        this._btn = $$('.btn-checkout')[0];
        },
        setup: function(superClass) {
            this.super = superClass;
            var _this = this;    
            
            Payment.prototype.save = Payment.prototype.save.wrap(function(paymentSave) {
                var validator = new Validation(this.form);
                if (this.validate() && validator.validate()) {
                    if (this.currentMethod == 'bambora_integrated') {
                        createOverlay();
                    } else {
                        paymentSave(); //return default method
                    }
                }
            });
        
        },
        changeMethod: function(superClass) {
        }        

    }
}

window.$BamboraCheckout = new BamboraCheckout();

if ($$(".checkout-onepage-index")[0] != undefined) {
    window.$BamboraCheckout.register(Bambora_Mage_Checkout,'Mage_Checkout');
    Event.observe(window,'load', function() {   
        if (window.$BamboraCheckout != undefined) {  
            window.$BamboraCheckout.setup({
                checkoutExtension:'Mage_Checkout',
            });
        }
    });    
    
}

if ($$(".firecheckout-index-index")[0] != undefined) {
    window.$BamboraCheckout.register(Bambora_Firecheckout_Checkout,'Firecheckout_Checkout');
    Event.observe(window,'load', function() {   
        if (window.$BamboraCheckout != undefined) {  
            window.$BamboraCheckout.setup({
                checkoutExtension:'Firecheckout_Checkout',
            });
        }
    });    
}

if ($$(".onestepcheckout-index-index")[0] != undefined) {
    window.$BamboraCheckout.register(Bambora_Idev_OnestepCheckout, 'Idev_OnestepCheckout');
    Event.observe(window,'load', function() {   
        if (window.$BamboraCheckout!=undefined) {  
            window.$BamboraCheckout.setup({
                checkoutExtension:'idev_onestepcheckout',
            });
        }
    });    
}