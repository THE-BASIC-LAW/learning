function bindAddress(event, uid, shop_station_id, title, desc, address) {
    event.stopPropagation();

    // if(lbsID == uid) {
    // 	alert("该点已绑定");
    // 	return;
    // }

    // 去除非法字符 ( )
    address = address.replace('(', ' ');
    address = address.replace(')', ' ');

    var data = {
        'openid' : openid,
        'stationid' : curSid,
        'lbsid' : uid,
        'shop_station_id' : shop_station_id,
        'title' : title,
        'desc' : desc,
        'address' : address
    };
    $.ajax({
        type: 'GET',
        url: settingUrl ,
        data: data ,
        dataType: 'JSON',
        success: function(data) {
            if(data.errcode == 0) {
                lbsID = uid;
                alert('绑定成功');
                wxApiCloseWindow();
            } else {
                alert("参数有误:" + data.errmsg);
                wxApiCloseWindow();
            }
        },
        error: function(e) {
            alert("error 参数有误");
            alert(JSON.stringify(e));
            wxApiCloseWindow();
        }
    });
}

function bindA(uid, shop_station_id, title, desc, address) {
    return "<a href='javascript:void(0);' class='bindAddress' onclick=\"bindAddress(event, " + uid + ", " + shop_station_id + ", '" + title + "', '" + desc + "', '" + address + "');\" style='float:right'> 绑定地址</a>";
}

//关闭微信页面
function wxApiCloseWindow() {
    WeixinJSBridge.invoke('closeWindow',{},function(res){
        if(res.err_msg == "close_window:error") {
            alert("关闭微信网页错误，请稍后重试，谢谢");
        }
    });
}
