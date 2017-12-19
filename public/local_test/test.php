<link  href="/local_test/cropperjs/dist/cropper.css" rel="stylesheet">
<script src="https://cdn.bootcss.com/jquery/3.2.1/jquery.min.js"></script>
<script src="/local_test/cropperjs/dist/cropper.js"></script>
<section style="margin: 30px auto; width: 550px">
    <section>
        <div style="padding-left: 80px">
            <input type="hidden" name="__token__" value="{$Request.token}" id="token">
            <input type="file" name="file" id="file" style="border: 0px solid black;" accept=".jpg,.jpeg,.png,.gif,.bmp,.tiff">
            <input class="layui-btn submitbutton" type="submit" value="上传">
            <div>
                <img id="demoImg" alt="" style="max-width: 100%">
            </div>
            <div style="width: 100px; height: 100px;">
                <img id="output" style="background-size: cover;">
            </div>
        </div>
    </section>
</section>

<script>

    jQuery(function($) {
        var cropper;
        $('.submitbutton').click(function(){
            var form_data = new FormData();
            form_data.append('myfile',$('#file')[0].files[0]);
            form_data.append('__token__', $('#token').val());
            if($('#file')[0].files[0] == undefined){
                layer.msg('请选择文件', {icon: 2});
                return;
            }
            var options = new Object();
            options['width']  = '100';
            options['height'] = '100';
            options['imageSmoothingQuality'] = 'high';
            var res = cropper.getCroppedCanvas(options);
            var file = res.toDataURL("image/png");
            var image = document.getElementById('output');
            image.src = file;
            console.log(res);
            console.log(file);
            return;

            $.ajax({
                url: "{$action}",
                type: 'post',
                data: form_data,
                contentType: false,
                processData: false,
                success: function(e) {
                    if(e.code == 0) {
                        layer.msg(e.msg, {icon: 1}, function(){
                            parent.layer.close(parent.layer.getFrameIndex(window.name)); // 关闭本iframe层
                            parent.location.reload(); // 父页面刷新
                        });
                    } else {
                        layer.msg(e.msg, {icon: 2});
                    }
                },
                error: function () {
                    layer.msg('内部错误', {icon: 2}, function(){
                    });
                }
            })
        })

        var image = document.getElementById('demoImg')
        $('#file').change(function () {
            var file=$('#file')[0].files[0];
            var reader=new FileReader();
            reader.onload= function (e) {
                if(cropper != undefined){
                    image.src = e.target.result;
                    cropper.replace(e.target.result);
                    return;
                } else {
                    image.src = e.target.result;
                }
                cropper = new Cropper(image, {
                    guides: false,
                    center: false,
                    restore: false,
                    minCanvasHeight:100,
                    minCanvasWidth:100,
                    dragMode: 'move',
                    highlight: false,
                    aspectRatio: 1 / 1,
                    cropBoxMovable: false,
                    cropBoxResizable: false,
                    toggleDragModeOnDblclick: false,
                    ready:function(){
                        var cropper = this.cropper;
                        var params = new Object();
                        var cavas_params = new Object();
                        params['top']   = 150;
                        params['left']   = 150;
                        params['width']  = 100;
                        params['height'] = 100;
                        cavas_params['minWidth'] = 100;
                        cropper.setCropBoxData(params);
                        cropper.setData()
                    }
                });
            };
            reader.readAsDataURL(file);
        });
    })
</script>