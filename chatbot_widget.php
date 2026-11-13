<!-- Viet Duc Pharmacy AI Chatbot -->
<link rel="stylesheet" href="chatbot.css?v=2">
<div id="vd-chatbot" class="vd-chatbot">
  <button type="button" id="vd-chat-toggle" class="vd-chat-toggle" aria-label="Mở trợ lý AI" aria-expanded="false"><span class="vd-chat-icon">✦</span><span class="vd-chat-toggle-text">Trợ lý AI</span></button>
  <section id="vd-chat-panel" class="vd-chat-panel" aria-label="Trò chuyện với trợ lý" hidden>
    <header class="vd-chat-header"><div class="vd-chat-avatar">✦</div><div class="vd-chat-headtext"><strong>Trợ lý Viet Duc</strong><small><span class="vd-online-dot"></span> Hỗ trợ mua sắm 24/7</small></div><button id="vd-chat-close" type="button" aria-label="Đóng">×</button></header>
    <div id="vd-chat-messages" class="vd-chat-messages" aria-live="polite"><div class="vd-msg vd-msg-bot">Xin chào! Mình có thể giúp bạn tìm sản phẩm, xem giá và thông tin có trong cửa hàng. Bạn cần hỗ trợ gì hôm nay?<div class="vd-quick-actions"><button type="button" data-question="Tìm sản phẩm vitamin">Tìm vitamin</button><button type="button" data-question="Tìm sản phẩm chăm sóc da">Chăm sóc da</button><button type="button" data-question="Cách liên hệ dược sĩ?">Liên hệ dược sĩ</button></div></div></div>
    <div class="vd-chat-disclaimer" id="vd-chat-live-status">Trợ lý AI đang hỗ trợ · Nhân viên có thể tiếp nhận bất cứ lúc nào.</div>
    <form id="vd-chat-form" class="vd-chat-form"><input id="vd-chat-input" type="text" maxlength="1000" placeholder="Nhập câu hỏi của bạn..." autocomplete="off" aria-label="Câu hỏi"><button type="submit" aria-label="Gửi tin nhắn">➤</button></form>
  </section>
</div>
<script src="chatbot.js?v=2" defer></script>
