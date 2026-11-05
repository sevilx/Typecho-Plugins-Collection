<!-- 评论过滤器插件 FuckAdComment -->
<style>
    .comment-action .dropdown-menu li>a{
        margin-right: 0;
    }
    .fuckAdRow{
        display: inline;position:relative;
    }
    /* 移动端 换行显示 */
    @media (max-width: 575px) {
        .fuckAdRow{display: block;position:relative;}
    }

    .fuckAdAction:hover{cursor: pointer}
    .fuckAdAction.active{
        font-weight:bold;
    }
</style>
<script type="text/javascript">
    let actionMap = {actionMap};
    if ('{pluginConfig}') {
        $('.typecho-list-operate .operate').append(
            '<button class="btn btn-s" onclick="window.location.href = \'{pluginConfig}\'" type="button">拦截配置</a>')
    }
    // 遍历每行数据加一个操作按钮
    $('.typecho-list-table tbody tr').each(function() {

        let rowHtml = `
            <div class="fuckAdRow">
            <a class="fuckAdAction">拉黑</a>
            <ul class="dropdown-menu">`;
        for (let key in actionMap) {
            if ($(this).data('comment')[key]){
                rowHtml+='<li ><a href="javascript:void(0);" action="'+ key +'">拉黑 '+ actionMap[key] +'</a></li>';
            }
        }
        rowHtml+= `</ul></div>`;
        $(this).find('.comment-action').append(rowHtml)

    })

    // 下拉菜单切换
    $('.fuckAdAction').on('click',function() { $(this).toggleClass('active').next('ul').toggle() })

    // 触发操作
    $('.fuckAdRow a[action]').on('click',function() {
        let data = {do: $(this).attr('action'),content:''}
            data.cid = $(this).parents('tr').children().first('td').find('input[type=checkbox]').val();
            let msg = "请确认需要拉黑评论的【"+actionMap[data.do]+"】内容\n 新评论 【"+actionMap[data.do]+"】 包含该内容时将会被拦截\n";

        data.content = $(this).parents('tr').data('comment')[data.do];
        data.content = prompt(msg,data.content)
        // 判断是否确定拉黑
        if(data.content !== null) {
            window.location.href = '{securityUrl}&'+ $.param(data)
        }
    })
</script>