### 登录API
* \Gini\Gapper\Client::isLoggedId，判断用户是否登录
    * 如果当前访问的app为user类型，且用户已经输入了正确的用户名密码，则返回true
    * 如果当前访问的app为group类型，且用户输入了正确的用户名密码 + 用户在gapper-server的组只有一个关联了该app，则返回true
* \Gini\Gapper\Client::getLoginStep
    * \Gini\Gapper\Client::STEP_LOGIN：尚未登录
    * \Gini\Gapper\Client::STEP_GROUP：登录成功，未选择组
* \Gini\Gapper\Client::chooseGroup($gid)，登录成功后选择组

* \Gini\Gapper\Client::login，gapper-client封装的登录流程，会有页面跳转
* \Gini\Gapper\Client::logout，用户登录出，登出之后跳转到gapper-client登录页面

* \Gini\IoC::construct('\Gini\Gapper\Client')->getUserInfo()，获取当前登录的用户信息
* \Gini\IoC::construct('\Gini\Gapper\Client')->getGroupInfo()，获取当前登录的用用户组信息

### 登录页面自定义
* 仅自定义页面的静态内容，何时弹出登录dialog有gapper-client控制
    * gapper.yml中添加配置
        
            # VIEWNAME: view/VIEWNAME.phtml
            login_view: VIEWNAME

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


### TODO
* GapperClient取消对Auth库的依赖
* login和logout的封装，取消跳转功能，仅返回成功/失败。
* 弱化/隐藏isLoggedIn，各APP对login的判断逻辑从getLoginStep开始，根据需要进行login和chooseGroup
* getUserInfo和getGroupInfo方法静态化
