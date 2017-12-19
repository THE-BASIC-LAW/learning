var maxCount = 32;

var map = new BMap.Map("map"); // 创建Map实例
var userLocation = null;
var userCity = null;
var curCity = null; // 默认城市
var nearByRes = null;
var nearByPoints = [];
var nearByPage = 0;
var NEARBY_DEFAULT = 100000; // 默认搜索范围
var nearByRadius = NEARBY_DEFAULT;
var toggleRefresh = true;

var LOCAL_SEARCH = 0;
var NEARBY_SEARCH = 1;
var BOUND_SEARCH = 2; // 暂时不用

var searchMode = NEARBY_SEARCH; // 默认搜索方式

var keyFilter = ["enable:1"]; // 过滤条件

var curPage = null, totalPage = null;

var showList = true;

var isLoading = false;

var dragcount = 0;

var lng = 0;

var lat = 0;

function toggle() {
    showList = !showList;
    if(showList) {
        $('#mapbox_new').css("height", "50%");
        $('#main_new').css("display", "block");
        $('#arrow_down').css("display", "block");
        $('#arrow_up').css("display", "none");
        $('#main_new').css("height", "50%");
    } else {
        $('#mapbox_new').css("height", "100%");
        $('#main_new').css("display", "none");
        $('#arrow_down').css("display", "none");
        $('#arrow_up').css("display", "block");
    }
}

function addStation(stationid) {
	$(".mask-addStation-bg").css("display","block");
	var stationName = $(":text[name='stationName']").val();
    var stationProvince = $("#get-province").val();
    var stationCity     = $("#get-city").val();
    var stationArea     = $("#get-area").val();
    var stationStreet   = $("#street").val();
    var stationDesc    = $(":text[name='stationDesc']").val();
    var shopType = $('#shop_type').val();
    var shopCost = $('#cost').val();
    var shopPhone = $('#phone').val();
    var shopeTime = $('#etime').val();
    var shopsTime= $('#stime').val();
    if (!stationName || !stationProvince || !stationCity || !stationArea || !stationStreet || !stationDesc || !shopPhone || !shopeTime || !shopsTime) {
		$(".mask-addStation-bg").css("display","none");
		alert('请填写所有项目');
    	return false;
	}
	$(".mask-bg").css("display","none");
    var url = '/maintain/station/'+stationid+'/add_shop';
    var shop_data = {
    	stationName: stationName,
        stationDesc:stationDesc,
        stationProvince: stationProvince,
        stationCity: stationCity,
        stationArea: stationArea,
        stationStreet:stationStreet,
        latitude: $('#add-shop-location').data('lat'),
        longitude: $('#add-shop-location').data('lng'),
        type: shopType,
        cost: shopCost,
        stime: shopsTime,
        etime: shopeTime,
        phone: shopPhone
    };
    $.ajax({
		url: url,
		type: 'POST',
		data: shop_data,
		success: function(e) {
            if (e.code == 0) {
                $(".mask-addStation-bg").css("display","none");
                alert('新增商铺并绑定机器成功!');
                window.location.reload();
            } else {
                $(".mask-addStation-bg").css("display","none");
                alert('新增失败:'+e.msg);
            }
		}
	});
}

!function() {
	// 初始化地图模块相关代码
	map.enableScrollWheelZoom(); //启用滚轮放大缩小 map.enableContinuousZoom(); //
	// 启用地图惯性拖拽，默认禁用
	// map.enableInertialDragging(); //
	// 启用连续缩放效果，默认禁用。 map.addControl(new
	// BMap.NavigationControl()); // 添加平移缩放控件
	//map.addControl(new BMap.OverviewMapControl()); // 添加缩略地图控件
	//map.addControl(new BMap.MapTypeControl()); // 添加地图类型控件
	// 初始化地图,设置中心点坐标和地图级别
//	map.centerAndZoom(new BMap.Point(116.404, 39.915), 15);
//	map.setCurrentCity("深圳"); //由于有3D图，需要设置城市哦

	location(); // 定位并查找附近的点
	// ==========================================

	// 检索模块相关代码
	var keyword = "", // 检索关键词
	page = 0, // 当前页码
	points = [];

	function mapChange(e) {
//		alert(e.type);
		if(searchMode == BOUND_SEARCH) {
			searchAction('', 0, BOUND_SEARCH);
		}
	}

	function mapChange1(e) {
		mapChange(e);
	}

	function mapChange2(e) {
		mapChange(e);
	}

	// 绑定检索按钮事件
	$('#searchBtn').bind('click', function() {
		keyword = $('#keyword').val();
		searchAction(keyword);
	});

	$('#nearby').bind('click', function() {
		searchMode = NEARBY_SEARCH;
		nearByRadius = $("#searchRadius").val();
		if(nearByRadius == '')
			nearByRadius = NEARBY_DEFAULT;
		if(userLocation != null) {
			changeCity(userCity);
			searchAction('', 0, NEARBY_SEARCH, userLocation);
			//map.panTo(userLocation);
		} else if(userLocation != null) {
			location();
		}
	});

	function location() {
		$.blockUI({
			overlayCSS:{'backgroundColor':'0xFF'},
			message: $("#loading_img"),
			css:{'background':'transparent', "border":'none'}
        });

		var geolocation = new BMap.Geolocation();
		var gc = new BMap.Geocoder();
		geolocation.getCurrentPosition(function(r) {
			if (this.getStatus() == BMAP_STATUS_SUCCESS) {
				userLocation = r.point;
				$('#add-shop-location').data('lng', r.point.lng);
				$('#add-shop-location').data('lat', r.point.lat);
				var mk = new BMap.Marker(userLocation);
				mk.setIcon(getIcon(10));
				map.addOverlay(mk);
				map.addEventListener("dragend",function(){
					// 移动结束，让地图点自动居中
					dragcount = 1;
				    if(mk){
				        mk.setPosition(map.getCenter());
                        $('#add-shop-location').data('lng', map.getCenter().lng);
                        $('#add-shop-location').data('lat', map.getCenter().lat);
				    }
				});
				console.log('您的位置：' + r.point.lng + ',' + r.point.lat);
				gc.getLocation(r.point, function(rs) {
					var addComp = rs.addressComponents;
					userCity = addComp.city;
					console.log(addComp.province + addComp.city + addComp.district +
					addComp.street + addComp.streetNumber);
                    console.log('get location');
                    $('#get-province').val(addComp.province);
                    $('#get-province').trigger('change', [addComp.province, 1]);
                    $('#get-city').val(addComp.city);
                    $('#get-city').trigger('change', [addComp.province, addComp.city, 1]);
                    $('#get-area').val(addComp.district);
                    $('#street').val(addComp.street + addComp.streetNumber);
					if(curCity == null || isInCurCity(userCity)) {
						changeCity(userCity);
						searchAction('', 0, NEARBY_SEARCH, userLocation);
					} else {
						searchAction('', 0, LOCAL_SEARCH);
					}
				});
				// map.panTo(r.point);
			} else {
				alert('定位失败,请允许获取位置信息,谢谢.failed' + this.getStatus());
				$.unblockUI();
			}

		}, {
			enableHighAccuracy : true
		});
		// 关于状态码
		// BMAP_STATUS_SUCCESS 检索成功。对应数值“0”。
		// BMAP_STATUS_CITY_LIST 城市列表。对应数值“1”。
		// BMAP_STATUS_UNKNOWN_LOCATION 位置结果未知。对应数值“2”。
		// BMAP_STATUS_UNKNOWN_ROUTE 导航结果未知。对应数值“3”。
		// BMAP_STATUS_INVALID_KEY 非法密钥。对应数值“4”。
		// BMAP_STATUS_INVALID_REQUEST 非法请求。对应数值“5”。
		// BMAP_STATUS_PERMISSION_DENIED 没有权限。对应数值“6”。(自 1.1 新增)
		// BMAP_STATUS_SERVICE_UNAVAILABLE 服务不可用。对应数值“7”。(自 1.1 新增)
		// BMAP_STATUS_TIMEOUT 超时。对应数值“8”。(自 1.1 新增)
	}

	/**
	 * 进行检索操作
	 *
	 * @param 关键词
	 * @param 当前页码
	 */
	function searchAction(keyword, page, type, location) {
		if(POI_TYPE) {
			searchPOIAction(keyword, page, type, location);
			return;
		}
		page = page || 0;
		type = typeof(type) != "undefined"? type : searchMode;
		var url = "http://api.map.baidu.com/geosearch/v3/local?callback=?"; // 城市区域内搜索
		switch(type) {
		case LOCAL_SEARCH:
			url = "http://api.map.baidu.com/geosearch/v3/local?callback=?";
			break;
		case NEARBY_SEARCH:
			url = "http://api.map.baidu.com/geosearch/v3/nearby?callback=?";
			if(location == null)
				location = userLocation; //默认是用户位置
			break;
		case BOUND_SEARCH:
			url = "http://api.map.baidu.com/geosearch/v3/bound?callback=?";
			break;
		}
		var data = {
			'q' : curCity + " " + keyword, // 检索关键字 只检索当前城市
			'page_index' : page, // 页码
			'filter' : keyFilter.join('|'), // 过滤条件
			//'filter' : '',
			'region' : curCity, // 城市名
			// 'scope' : '2', // 显示详细信息
			'geotable_id' : GEOTABLE_ID, //test 117126, jjsan 119779
			'sortby' : 'distance:1',
			'radius' : nearByRadius,
			'bounds' : getBounds(),
			'ak' : BMAP_AK // 用户ak
		};
		if (location) {
			data['location'] = location.lng + ',' + location.lat;
		}

        isLoading = true;

		// console.log("************************8");
		// console.log(url);
		console.log(data);
		// console.log("************************8");
		$.ajax({
			type: "get",
            contentType: "application/json; charset=utf-8",
            dataType: "json",
            url: url,
            data: data,
            success: function (data) {
            console.log(data);
		   if(data.status != 0) {
            		alert("地图服务暂不可用,请稍后再试,谢谢!");
            		return;
            	}

            	renderMap(data, type, location);
            	if(page == 0) {
            		if(points.length == 0) {
            			map.centerAndZoom(curCity);
            			var tip = '<p style="border-top:1px solid #DDDDDD;padding-top:10px;text-align:center;text-align:center;font-size:18px;" class="text-warning">抱歉，该城市暂时没有站点信息</p>';
            			$('#listBoby').html($(tip));
            		} else {
            			map.setViewport(points);
            		}
            	}
            	curPage = page; // start 0
            	totalPage = Math.ceil(data.total / 10); //start 1
            	$("#listContainer").endlessScroll({
        			fireOnce: false,
        			fireDelay: false,
        			loader: '',
        			callback: function(p){
                        if(isLoading)
                            return;
        				$("#loading_img").css('display', 'block');
        				searchAction(keyword, curPage+1, type, location);
        			},
        			ceaseFire: function() {
        		        return curPage+1 >= totalPage; // end
        		    }
        		});
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
            	//baidu server error retry
            	console.log(textStatus + ", " + errorThrown);
            	//searchAction(keyword, page, type, location);
            	alert("地图服务暂不可用,请稍后再试,谢谢!");
            },
            complete: function() {
            	$.unblockUI();
            	$("#loading_img").css('display', 'none');
            	$('#main_new').css('display', 'block');
            	// $('#changecity').css('display', 'block');
            	//map.addControl(new BMap.ScaleControl()); // 添加比例尺控件
                isLoading = false;
            }
        });
	}

	function searchPOIAction(keyword, page, type, location) {
		page = 0;
		type = typeof(type) != "undefined"? type : searchMode;
		var url = "http://api.map.baidu.com/geodata/v3/poi/list"; // 城市区域内搜索
		var data = {
			'page_index' : page, // 页码
			// 'scope' : '2', // 显示详细信息
			'geotable_id' : GEOTABLE_ID, //test 117126, jjsan 119779
			'page_size' : 200,
			'ak' : BMAP_AK // 用户ak
		};

		$.ajax({
			type: "get",
            contentType: "application/json; charset=utf-8",
            dataType: "jsonp",
            url: url,
            data: data,
            success: function (data) {
            	if(data.status != 0) {
            		alert("地图服务暂不可用,请稍后再试,谢谢!");
            		return;
            	}
            	data['contents'] = data['pois'];
            	delete data['pois'];
            	data['contents'].sort(function(a, b) {
					var da = map.getDistance(new BMap.Point(a.location[0], a.location[1]), location);
					var db = map.getDistance(new BMap.Point(b.location[0], b.location[1]), location);
					return da > db ? 1:-1;
				});
            	renderMap(data, type, location);
            	if(page == 0) {
            		if(points.length == 0) {
            			map.centerAndZoom(curCity);
            			var tip = '<p style="border-top:1px solid #DDDDDD;padding-top:10px;text-align:center;text-align:center;font-size:18px;" class="text-warning">抱歉，该城市暂时没有站点信息</p>';
            			$('#listBoby').html($(tip));
            		} else {
            			map.setViewport(points);
            		}
            	}
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
            	//baidu server error retry
            	console.log(textStatus + ", " + errorThrown);
            	//searchAction(keyword, page, type, location);
            	alert("地图服务暂不可用,请稍后再试,谢谢!");
            },
            complete: function() {
            	$.unblockUI();
            	$("#loading_img").css('display', 'none');
            	$('#main_new').css('display', 'block');
            	// $('#changecity').css('display', 'block');
            	//map.addControl(new BMap.ScaleControl()); // 添加比例尺控件
            }
        });
	}

	// 办定列表/地图模式切换事件
	$('#chgMode').bind('click', function() {
		$('#listBox').toggle('normal');
		$('#mapBox').toggle('normal', function() {
			if ($('#mapBox').is(":visible")) { // 单显示地图时候，设置最佳视野
				//mapAddEvents();
				if(toggleRefresh) {
					if(points.length != 0) {
						map.setViewport(points);
					}
					else {
						map.centerAndZoom(curCity);
					}
				}
			} else {
				//mapRemoveEvents();
			}

			toggleRefresh = false;
		});
	});

	function getBounds() {
		var bounds = map.getBounds();
		var left = bounds.getSouthWest();
		var right = bounds.getNorthEast();
		if(left == null || right == null)
			return '';
		return left.lng + ',' + left.lat + ';' + right.lng + ',' + right.lat;
	}

	/**
	 * 渲染地图模式
	 *
	 * @param result
	 * @param page
	 */
	function renderMap(res, type, location) {
		$('#waiting').css('display', 'none');
		$('#mainContainer').css('display', 'block');


		if(type == NEARBY_SEARCH) {
			nearByRes = res;
			nearByPoints = points;
		}

		points = renderData(res, type, location);

		if(type == BOUND_SEARCH && nearByRes != null) {
			points = renderData(nearByRes, NEARBY_SEARCH, userLocation);
		}

		if (isInCurCity(userCity)) {
			var mk = new BMap.Marker(userLocation);
			mk.setIcon(getIcon(10));
			// 去除固定地图点
			// map.addOverlay(mk);
			points.push(userLocation);
//			nearByPoints.push(userLocation);
		}

	}
	;

	function isInCurCity(city) {
		if ((curCity == city || (curCity+"市") == city) || curCity == (city+"市")) {
			return true;
		}
		return false;
	}

	function changeCity(city) {
		curCity = city;
		$('#curCity').html(city);
		$('#changeCityAction').attr('href', '/lbs/citylist.php?curcity=' + city);
	}

	function mapShow() {
		if($('#mapBox').is(":visible"));
			return false;
		return true;
	}

	function renderData(res, type, location) {
		var points = [];
		var content = res.contents;
		var nearbyContent = null;
		if(nearByRes != null && nearByRes.contents.length != 0)
			nearbyContent = nearByRes.contents;

		if (type != BOUND_SEARCH && content.length == 0) {
//			var tip = '<p style="border-top:1px solid #DDDDDD;padding-top:10px;text-align:center;text-align:center;font-size:18px;" class="text-warning">抱歉，您所在的城市没有找到站点信息，请重新查询</p>';
//			$('#listBoby')
//			.html($(tip));
		} else {
			$
					.each(
							content,
							function(i, item) {
								if(POI_TYPE) {
									if(keyFilter[0] == "enable:1" && item.enable == 0)
										return true; //continue
									if(keyFilter[0] == "enable:0" && item.enable == 1)
										return true; //continue
								}
								var point = new BMap.Point(item.location[0],
										item.location[1]), marker = new BMap.Marker(
										point);

								var exist = false;
								if(type == BOUND_SEARCH && nearbyContent != null) {
									$.each(nearbyContent, function(i, nearbyItem) {
										if(nearbyItem.uid == item.uid) {
											exist = true;
											return false; //break;
										}
									});
									if(exist)
										return true; //continue;
								}
								points.push(point);
								marker.addEventListener('click', showInfo);

								var distance = '';
								if(isInCurCity(userCity)) {
									distance = map.getDistance(userLocation, point);
									distance = distanceUnit(distance);
								}
								var bindALabel = typeof(bindA) != "undefined"? bindA(item.uid, item.sid, item.title, item.desc, item.address) : "";
								if(type != BOUND_SEARCH) {
									//list start
									var tr = $("<tr><td>" + item.title + " (" + item.usable + "/" + (item.empty) + ")" + "<br/>地址："
											+ item.address + "</td></tr>").click(switchToShowInfo);

                                        var navigation = "http://api.map.baidu.com/direction?origin=latlng:" + userLocation.lat + "," + userLocation.lng + "|name:我的位置&destination=latlng:" + point.lat + "," + point.lng + "|name:" + item.title + "&mode=driving&region=" + userCity + "&output=html&src=街借伞|街借伞驿站";

										var desc = (typeof(item.desc) == "undefined" || item.desc == '') ? '' : ('(' + item.desc + ')');
										var basicBlock = $('<p style="font-size:16px">' + item.title + '</p><p class="libs-desc">' + item.address + desc + '</p>');
										//var distanceBlock = $("<div style='float:right;font-size:12px; text-align:center'>" + distance + "<div style='font-size:14px'><a href=" + navigation + ">到这里去</a></div></div>");
										var distanceBlock = $("<div style='font-size:12px; text-align:center; font-size:14px;'>" + distance + "</div>");
										var td = $('<td width="50%"></td>');
										tr = $('<tr></tr>');

										td.append(basicBlock);
										tr.append(td);

										td = $('<td width="20%" style="text-align:center"></td>');
										td.append(distanceBlock);
										td.append($(bindALabel));
										tr.append(td);

										tr.append(td);

										tr.click(switchToShowInfo);
									}
									$('#listBoby').append(tr);
									//list end
								//}

								function switchToShowInfo() {
									$('#chgMode').trigger('click');
									showInfo();
								}

								function showInfo() {
//									var content = "<p>" + item.title + "</p>"
//											    + "<p>地址：" + item.address + "</p>";
									var content = "地址：" + item.address;
									var title = item.title + " (" + item.usable + "/" + (item.empty) + ")";
									if(type == NEARBY_SEARCH)
										title += " (" + distance + ")";
									// 创建检索信息窗口对象
									var infoWindow = new BMap.InfoWindow(
											content, {
												title : title + bindALabel, // 标题
												enableAutoPan : true, // 自动平移
											});
									map.openInfoWindow(infoWindow,point);
								};
								map.addOverlay(marker);
							});
		}
		return points;
	}

    //渲染搜索列表页面
    function renderDataNew(e) {
//商铺
        var shop = e.data.shop[0];
        var shop_station = e.data.shop_station;
        var p = "";
        p += '<tr class="listBodyUp">';
        p +=       '<td class="shopClick">';
        p +=             '<p style="font-size:16px"> '+ shop.name +' </p>';
        p +=             '<p class="libs-desc"> '+ shop.locate +' </p>';
        p +=       '</td>';
        p +=       '<td class="bindShopBtn">';
        //p +=             '<span>222</span>'
        //p +=           '<span> <a onclick="bindShop(event, ' + shop.id + ' )";            id="bindShop">绑定地址</a> </span>';
        p +=             '<span> <a onclick="bindShopBtn(' + shop.id + ');" class="bindShop bindShopBtn">绑定地址</a> </span>';
        p +=       '</td>';
        p += '</tr>';
        $('#listBoby').html(p);
//商铺station
        var c = '';
        for(var i in shop_station){
            var point = new BMap.Point(shop_station[i].longitude,
                shop_station[i].latitude), marker = new BMap.Marker(
                point);
            var bindALabel = typeof(bindA) != "undefined"? bindA(shop_station[i].lbsid, shop_station[i].id, shop_station[i].title, shop_station[i].desc, shop_station[i].address) : "";
            console.log(shop_station[i]);
            var desc = (typeof(shop_station[i].desc) == "undefined" || shop_station[i].desc == '') ? '' : ('(' + shop_station[i].desc + ')');

            c += '<tr class="listBodyDown" style="display:none">';
            c +=       '<td>';
            c +=             '<p style="font-size:16px"> '+ shop_station[i].title +' </p>';
            c +=             '<p class="libs-desc"> '+ shop_station[i].address + desc +' </p>';
            c +=       '</td>';
            c +=       '<td class="bindAbtn">';
            // c +=          '<span style="font-size:12px; font-size:14px;"> '+ distance +'</span>'
            c +=             '<span> '+ bindALabel +'</span>';
            c +=       '</td>';
            c += '</tr>';
        }
        $('#listBoby').append(c);

        $(".shopClick").click(function(){
            $(".listBodyDown").toggle();
        })
    }
	function resetMap() {
		$('#keyword').val('');
		$('#selectedValue').html('');
		searchAction('');
	}

	function mapAddEvents() {
		map.addEventListener('dblclick', mapChange);
		map.addEventListener('zoomend', mapChange1);
		map.addEventListener('moveend', mapChange2);
	}

	function mapRemoveEvents() {
		map.removeEventListener('dblclick', mapChange);
		map.removeEventListener('zoomend', mapChange1);
		map.removeEventListener('moveend', mapChange2);
	}

	function getIcon(i) {
		return new BMap.Icon(
				"http://api.map.baidu.com/img/markers.png", new BMap.Size(
						23, 25), {
					offset : new BMap.Size(10, 25), // 指定定位位置
					imageOffset : new BMap.Size(0, 0 - i * 25)
				// 设置图片偏移
				});
	}

	function distanceUnit(meter) {
		if(meter < 1000) {
			return Math.ceil(meter) + "米"; //取整
		} else {
			meter = (meter / 1000).toFixed(1);
			var meterInt = parseInt(meter);
			return (meter == meterInt ? meterInt : meter) + "千米";
		}
	}

	// ======================= 城市 选择 ==========================//
	// 创建CityList对象，并放在citylist_container节点内
	var myCl = new BMapLib.CityList({
		container : "citylist_container",
		map : map
	});

	// 给城市点击时，添加相关操作
	myCl.addEventListener("cityclick", function(e) {
		// 修改当前城市显示
		document.getElementById("curCity").innerHTML = e.name;
		// 点击后隐藏城市列表
		document.getElementById("cityList").style.display = "none";
		curCity = e.name;
		searchMode = LOCAL_SEARCH;
		if(isInCurCity(userCity)) {
			searchMode = NEARBY_SEARCH;
		}
		resetMap();
	});
	// 给“更换城市”链接添加点击操作
	/*document.getElementById("curCityText").onclick = function() {
		var cl = document.getElementById("cityList");
		if (cl.style.display == "none") {
			cl.style.display = "";
		} else {
			cl.style.display = "none";
		}
		//mapRemoveEvents();
	};*/
	// 给城市列表上的关闭按钮添加点击操作
	/*document.getElementById("popup_close").onclick = function() {
		var cl = document.getElementById("cityList");
		if (cl.style.display == "none") {
			cl.style.display = "";
		} else {
			cl.style.display = "none";
		}
		if(searchMode == BOUND_SEARCH) {
			//mapAddEvents();
		}
	};*/
}();

function formToggle() {
	$('form').css("display","block");
	$(".mask-bg").css("display","block");
}

function formCancel(){
	$('form').css("display","none");
	$(".mask-bg").css("display","none");
}

function renderDataNew(e) {
//商铺
    var shop = e.data.shop[0];
    var shop_station = e.data.shop_station;
    var p = "";
    p += '<tr class="listBodyUp">';
    p +=       '<td class="shopClick">';
    p +=             '<p style="font-size:16px"> '+ shop.name +' </p>';
    p +=             '<p class="libs-desc"> '+ shop.locate +' </p>';
    p +=       '</td>';
    p +=       '<td class="bindShopBtn">';
    p +=             '<span> <a onclick="bindShopBtn(' + shop.id + ');" class="bindShop bindShopBtn">绑定地址</a> </span>';
    p +=       '</td>';
    p += '</tr>';
    $('#listBoby').html(p);
//商铺station
    var c = '';
    for(var i in shop_station){
        var point = new BMap.Point(shop_station[i].longitude,
            shop_station[i].latitude), marker = new BMap.Marker(
            point);
        var bindALabel = typeof(bindA) != "undefined"? bindA(shop_station[i].lbsid, shop_station[i].id, shop_station[i].title, shop_station[i].desc, shop_station[i].address) : "";
        console.log(shop_station[i]);
        var desc = (typeof(shop_station[i].desc) == "undefined" || shop_station[i].desc == '') ? '' : ('(' + shop_station[i].desc + ')');

        c += '<tr class="listBodyDown" style="display:none">';
        c +=       '<td>';
        c +=             '<p style="font-size:16px"> '+ shop_station[i].title +' </p>';
        c +=             '<p class="libs-desc"> '+ shop_station[i].address + desc +' </p>';
        c +=       '</td>';
        c +=       '<td class="bindAbtn">';
        c +=             '<span> '+ bindALabel +'</span>';
        c +=       '</td>';
        c += '</tr>';
    }
    $('#listBoby').append(c);

    $(".shopClick").click(function(){
        $(".listBodyDown").toggle();
    })
}


//点击绑定出现摆放位置输入框
function bindShopBtn(shop_id){
    $(".mask-bg-small").css("display","block");
    $('#bind_shop').on('click', function(event) {
        $(".mask-position-bg").css("display","block");
        bindShop(event, shop_id, $(":text[name='desc']").val());
    });
    console.log(shop_id);
}


function bindShop(event, shop_id, desc) {
    event.stopPropagation();

    $.ajax({
        type: 'GET',
        url: 'index.php?mod=wechat&act=shop&opt=init_addr&do=bind_shop',
        data: {
            'stationid' : curSid,
            'shop_id' : shop_id,
            'desc' : desc
        },
        dataType: 'JSON',
        success: function(data) {
            if(data.status == 0) {
                lbsID = data.id;
                alert('绑定成功');
                wxApiCloseWindow();
            } else {
                alert("参数有误:" + data.message);
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

$(".cancel").click(function(){
    $(".mask-bg-small").css("display","none")
})

