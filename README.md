### 登录API
* \Gini\Gapper\Client::getLoginStep
    * \Gini\Gapper\Client::STEP\_LOGIN：尚未登录
    * \Gini\Gapper\Client::STEP\_GROUP：登录成功，未选择组
    * \Gini\Gapper\Client::STEP\_DONE：登录成功
* \Gini\Gapper\Client::chooseGroup($gid)，登录成功后选择组
* \Gini\Gapper\Client::loginByUserName($username)，以用户名登陆
* \Gini\Gapper\Client::loginByToken($token)，以token登陆

* \Gini\Gapper\Client::logout，用户登录出

* \Gini\Gapper\Client::goLogin: 跳转页面到登陆页

* \Gini\Gapper\Client::getUserName()，获取当前登录的用户username
* \Gini\Gapper\Client::getUserInfo()，获取当前登录的用户信息
* \Gini\Gapper\Client::getGroupInfo()，获取当前登录的用用户组信息

### 登录页面自定义
* 仅自定义页面的静态内容，何时弹出登录dialog有gapper-client控制
    * gapper.yml中添加配置
        
            # VIEWNAME: view/VIEWNAME.phtml
            # 登录谈层背景页面自定义
            login_view: VIEWNAME
            # VIEWNAME: view/401.phtml
            # 登录的用户没有权限访问APP时，会提示401错误
            # 允许各个APP自定义401错误内容
            login_error_401: VIEWNAME

    * 允许加载自定义脚本
            # 在login_view页面
            <div data-require="requirejs规范的模块名"></div>

* app独立开发登录页面
    * 通过\Gini\Gapper\Client::isLoggedIn()判断用户是否登录，如未登录则展示自定义view
    * 自定义view中在何时的时机调用登录dialog

            <script>
                require({
                    baseUrl: 'assets/js'
                }, ['gapper/client/login'], function(handler) {
                    handler.showLogin();
                });
            </script>

### APP间跳转
    
    /gapper/client/go/CLINET_ID?redirect=http://****/***

### 用户验证模块的约定[gapper-auth-example](https://github.com/pihizi/gini-gapper-auth-example)
* 注入/ajax/gapper/auth/EXAMPLE的subpath，并提供以下功能
    * /ajax/gapper/auth/EXAMPLE/getForm: 获取登录用的表单

            <div class="modal-header"><?=H(T('LOGIN'))?></div>
            <form class="modal-body form gapper-auth-login-form" method="POST" action="ajax/gapper/auth/EXAMPLE/login"><dl class="dl-horizontal">
                <dt class="text-center">
                    <div class="app-icon">
                        <div class="text-center app-icon-image"><img src="<?=H($info->icon)?>" /></div>
                        <div class="text-center app-icon-title"><?=H($info->name)?></div>
                    </div>
                </dt>
                <dd>
                    <div class="gapper-auth-login-form-li form-group"><input class="form-control" type="text" name="username" placeholder="Email" /></div>
                    <div class="gapper-auth-login-form-li form-group"><input class="form-control" type="password" name="password" placeholder="Password" /></div>
                    <div class="gapper-auth-login-form-li form-group"><input class="form-control btn btn-primary" type="submit" /></div>
                </dd>
            </dl></form>

    * /ajax/gapper/auth/EXAMPLE/login: 登录表单的提交地址，该地址在getForm的form action中指定

### 配置文件范例

* gapper.yml

        ---
        # RPC 
        client_id: ******
        client_secret: ******
        url: http://****/***

        # login ui
        login_view: gapper/client/login
        ...

* site.yml

        ---
        title: SITE-TITLE
        ...

* system.yml

        ---
        timezone: Asia/Shanghai
        locale: en_US
        ...
