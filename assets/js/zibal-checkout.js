const zibal_settings = window.wc.wcSettings.getSetting("WC_Gateway_Zibal_data", {});
const zibal_label =
  window.wp.htmlEntities.decodeEntities(zibal_settings.title) ||
  window.wp.i18n.__("زیبال", "zibal-woocommerce");

const Zibal_icon = Object(window.wp.element.createElement)("img", {
  src: zibal_settings.icon,
  alt: window.wp.htmlEntities.decodeEntities(zibal_settings.title),
  style: { marginLeft: "10px", display: "inline-block" },
});

const zibal_label_with_icon = window.wp.element.createElement("span", null, [
  Zibal_icon,
  zibal_label,
]);

const zibal_Content = () => {
  return window.wp.htmlEntities.decodeEntities(
    zibal_settings.description ||
      "پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال "
  );
};

const Zibal_Block_Gateway = {
  name: "WC_Gateway_Zibal",
  label: zibal_label_with_icon,
  content: Object(window.wp.element.createElement)(zibal_Content, null),
  edit: Object(window.wp.element.createElement)(zibal_Content, null),
  canMakePayment: () => true,
  ariaLabel: zibal_label,
  supports: {
    features: zibal_settings.supports,
  },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Zibal_Block_Gateway);
