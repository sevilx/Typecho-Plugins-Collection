/* PostToc 1.4.1 — 前端目录逻辑（无依赖，defer 加载） */
function postTocInit() {
    "use strict";

    // pjax / 无限加载重复注入脚本时，先撤掉上一次的全局监听，避免监听器累积
    if (window.__postTocAbort) {
        try { window.__postTocAbort.abort(); } catch (e) {}
    }
    var abort = typeof AbortController === 'function' ? new AbortController() : null;
    window.__postTocAbort = abort;
    var opt = function (options) {
        if (abort) options.signal = abort.signal;
        return options;
    };

    // 后台配置由插件在 head 里以全局变量输出；老站点升级或缓存页面里
    // 可能缺项，这里统一兜底，避免出现 undefined / NaN 让目录定位失效。
    var cfg = {
        defaultDisplay:  typeof defaultDisplay   !== 'undefined' ? defaultDisplay   : '1',
        mobileDisplay:   typeof mobileDisplay    !== 'undefined' ? mobileDisplay    : '1',
        navbarOffset:    typeof navbarOffset     !== 'undefined' ? navbarOffset     : '10',
        verticalOffset:  typeof verticalOffset   !== 'undefined' ? verticalOffset   : '0',
        horizontalOffset:typeof horizontalOffset !== 'undefined' ? horizontalOffset : '0',
        textColor:       typeof textColor        !== 'undefined' ? textColor        : '#777777',
        activeTextColor: typeof activeTextColor  !== 'undefined' ? activeTextColor  : '#bc6462',
        backgroundColor: typeof backgroundColor  !== 'undefined' ? backgroundColor  : '#C8C8C870'
    };
    var num = function (v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; };
    var raf = window.requestAnimationFrame
        ? window.requestAnimationFrame.bind(window)
        : function (cb) { return window.setTimeout(cb, 16); };
    var reduceMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // 手机端按后台设置隐藏目录
    if (cfg.mobileDisplay === '0' && window.innerWidth <= 768) {
        var staleToc = document.querySelector('#post-toc');
        var staleBtn = document.querySelector('.toc-toggle');
        if (staleToc) staleToc.remove();
        if (staleBtn) staleBtn.remove();
        return;
    }

    // 兼容常见主题的正文容器（DUX 为 .article-content）
    var selectors = [
        '.post-content', '.entry-content', '.article-content',
        '.markdown-body', '.content-body', '.joe_detail'
    ];
    var content = null;
    for (var i = 0; i < selectors.length; i++) {
        content = document.querySelector(selectors[i]);
        if (content) break;
    }
    if (!content) {
        return; // 不是文章页，安静退出，不往控制台刷错误
    }

    // 幂等：pjax / 无限加载重复执行时不叠加目录
    var existing = document.getElementById('post-toc');
    if (existing) existing.remove();
    var existingToggle = document.getElementById('toc-toggle');
    if (existingToggle) existingToggle.remove();

    var toc = document.createElement('nav');
    toc.id = 'post-toc';
    toc.setAttribute('role', 'navigation');
    toc.setAttribute('aria-label', '文章目录');
    toc.innerHTML = '<h3>目录</h3><ul></ul>';
    content.parentNode.insertBefore(toc, content);

    // 后台颜色只写 CSS 变量，且仅在管理员真的改过默认值时写。
    // 原版把 #777777 直接写进每个 <a> 的 inline style，内联样式压过 CSS，
    // 深色模式下目录文字会变成一块看不清的灰。顺便校验颜色格式，
    // 避免后台输入被当成任意 CSS 注入。
    var isColor = function (v) { return /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(v); };
    var userColors = [
        ['textColor', '#777777', '--toc-link-color-user'],
        ['activeTextColor', '#bc6462', '--toc-active-link-color-user'],
        ['backgroundColor', '#C8C8C870', '--toc-active-bg-user']
    ];
    userColors.forEach(function (item) {
        var value = String(cfg[item[0]] || '').trim();
        if (value !== '' && value.toUpperCase() !== item[1].toUpperCase() && isColor(value)) {
            toc.style.setProperty(item[2], value);
        }
    });

    // 过滤掉主题卡片描述里的标题（原版漏了类选择器的点号）
    var headings = Array.prototype.filter.call(
        content.querySelectorAll('h1, h2, h3, h4, h5, h6'),
        function (h) { return !h.closest('.joe-card-describe'); }
    );

    var tocList = toc.querySelector('ul');
    if (headings.length === 0) {
        toc.remove();
        return;
    }

    var minLevel = 7;
    headings.forEach(function (h) {
        minLevel = Math.min(minLevel, parseInt(h.tagName.substring(1), 10));
    });

    headings.forEach(function (heading, index) {
        // 保留作者手写或 Markdown 解析器生成的 id，避免破坏文内锚点
        var id = heading.id || ('heading-' + index);
        heading.id = id;

        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = '#' + id;
        a.textContent = heading.textContent;
        li.style.marginLeft = ((parseInt(heading.tagName.substring(1), 10) - minLevel) * 20) + 'px';
        li.appendChild(a);
        tocList.appendChild(li);
    });

    // 创建时（CSS 还是 300px 定宽、fixed 定位）量一次宽度并缓存，
    // 避免切换成内联卡片后 offsetWidth 变成正文宽度、导致 resize 再也切不回来。
    var tocWidth = toc.offsetWidth || 300;

    function clearPositionStyles() {
        ['position', 'top', 'left', 'right', 'bottom', 'width'].forEach(function (prop) {
            toc.style[prop] = '';
        });
    }

    function setTocPosition() {
        // 移动端固定走「右下角悬浮 + 按钮显隐」，与 CSS 的 max-width:768px 一致
        if (window.innerWidth <= 768) {
            toc.classList.remove('toc-inline');
            clearPositionStyles();
            toc.style.position = 'fixed';
            toc.style.bottom = '70px';
            toc.style.right = '20px';
            toc.style.left = 'auto';
            toc.style.top = 'auto';
            toc.style.width = 'min(300px, calc(100vw - 40px))';
            return;
        }

        // 只有左侧留白真的放得下「目录宽度 + 间距」时，才用经典悬浮布局。
        // 1366 / 1440 等常见宽度下留白不足，原来的 Math.max(12, ...) 会把目录
        // 强行压到文章标题上；这时降级成正文流里的普通卡片，绝不遮挡内容。
        // 65px 是原插件调过的间距：rect.left 是正文文字的左边界，而文章卡片
        // 本身还有一圈左内边距，只留 20~30px 会让目录右缘贴住卡片边框。
        var gutter = 65;
        var left = content.getBoundingClientRect().left
            - tocWidth - gutter + num(cfg.horizontalOffset);

        if (left < 12) {
            toc.classList.add('toc-inline');
            clearPositionStyles();
            return;
        }

        toc.classList.remove('toc-inline');
        clearPositionStyles();
        toc.style.position = 'fixed';
        toc.style.top = Math.max(80, 200 + num(cfg.verticalOffset)) + 'px';
        toc.style.left = Math.round(left) + 'px';
        toc.style.right = 'auto';
        toc.style.bottom = 'auto';
    }
    setTocPosition();
    window.addEventListener('resize', setTocPosition, opt({ passive: true }));

    toc.addEventListener('click', function (event) {
        if (event.target.tagName.toLowerCase() !== 'a') return;
        event.preventDefault();
        var targetId = event.target.getAttribute('href').substring(1);
        var targetElement = document.getElementById(targetId);
        if (!targetElement) return;
        // offsetTop 只相对 offsetParent，DUX 的多层 article 容器里会偏；
        // 改用视口坐标 + 页面滚动量，才能准确落到标题上。
        var top = targetElement.getBoundingClientRect().top
            + (window.pageYOffset || document.documentElement.scrollTop || 0)
            - num(cfg.navbarOffset);
        window.scrollTo({
            top: Math.max(0, top),
            behavior: reduceMotion ? 'auto' : 'smooth'
        });
        history.pushState(null, null, '#' + targetId);
    });

    function highlightCurrentHeading() {
        var currentId = null;
        for (var j = 0; j < headings.length; j++) {
            if (headings[j].getBoundingClientRect().top <= 100) {
                currentId = headings[j].id;
            } else {
                break;
            }
        }
        // 高亮完全交给 CSS 的 .active，深浅色与后台自定义色都能生效
        tocList.querySelectorAll('a').forEach(function (a) {
            a.classList.toggle('active', a.getAttribute('href').substring(1) === currentId);
        });
    }

    // 滚动节流：长文里不再每个滚动事件都遍历所有标题、跑一次 DOM 查询
    var ticking = false;
    window.addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        raf(function () {
            highlightCurrentHeading();
            ticking = false;
        });
    }, opt({ passive: true }));
    highlightCurrentHeading();

    // 拖拽目录（仅桌面端悬浮布局）
    var isDragging = false, startX, startY, initialLeft, initialTop;
    toc.addEventListener('mousedown', function (event) {
        // 内联卡片在正文流里，拖拽既无意义又会阻止用户选中文字
        if (toc.classList.contains('toc-inline') || window.innerWidth <= 768) return;
        if (event.target.tagName.toLowerCase() === 'a') return;
        event.preventDefault();
        isDragging = true;
        startX = event.clientX;
        startY = event.clientY;
        initialLeft = toc.offsetLeft;
        initialTop = toc.offsetTop;
        document.addEventListener('mousemove', onMouseMove, opt({}));
        document.addEventListener('mouseup', onMouseUp, opt({}));
        toc.style.transform = 'scale(1.05)';
    });
    function onMouseMove(event) {
        if (!isDragging) return;
        toc.style.left = (initialLeft + event.clientX - startX) + 'px';
        toc.style.top = (initialTop + event.clientY - startY) + 'px';
    }
    function onMouseUp() {
        isDragging = false;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        toc.style.transform = 'scale(1)';
    }
    toc.ondragstart = function () { return false; };

    // 显示 / 隐藏按钮
    var tocToggle = document.createElement('button');
    tocToggle.type = 'button';
    tocToggle.id = 'toc-toggle';
    tocToggle.className = 'toc-toggle';
    tocToggle.setAttribute('aria-label', '显示或隐藏文章目录');
    document.body.appendChild(tocToggle);

    var hidden = cfg.defaultDisplay === '0';
    function syncToggle() {
        toc.classList.toggle('hidden', hidden);
        tocToggle.textContent = hidden ? '显示目录' : '隐藏目录';
        tocToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    }
    syncToggle();
    tocToggle.addEventListener('click', function () {
        hidden = !hidden;
        syncToggle();
    });
}

// defer 脚本执行时 readyState 多为 interactive；pjax 动态注入时也不会再
// 等一个不会到来的 DOMContentLoaded，两种情况都能初始化。
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', postTocInit, { once: true });
} else {
    postTocInit();
}
