<?php
#-------------------------------------------------------------------------------
# Copyright 2019 NAVER Corp
# 
# Licensed under the Apache License, Version 2.0 (the "License"); you may not
# use this file except in compliance with the License.  You may obtain a copy
# of the License at
# 
#   http://www.apache.org/licenses/LICENSE-2.0
# 
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.  See the
# License for the specific language governing permissions and limitations under
# the License.
#-------------------------------------------------------------------------------

namespace Plugins;

use Plugins\Candy;
use Plugins\PerRequestPlugins;

///@hook:app\AccessRemote::\curl_setopt
///@hook:app\AccessRemote::\curl_exec
///@hook:app\AccessRemote::\curl_close
class NextSpanPlugin extends Candy
{
    /**
     * hook CURLOPT_HTTPHEADER
     * @param $ch
     */
    private function handleHttpHeader($ch,&$headers)
    {
        if(PerRequestPlugins::instance()->traceLimit()){
            $headers[] = 'Pinpoint-Sampled:s0';
            return ;
        }

        $headers[] ='Pinpoint-Sampled:s1';
        $headers[] ='Pinpoint-Flags:0';
        $headers[] ='Pinpoint-Papptype:1500';
        $headers[] ='Pinpoint-Pappname:'.pinpoint_app_name();

        $headers[] = 'Pinpoint-Host:'.$this->getHostFromURL(curl_getinfo($ch,CURLINFO_EFFECTIVE_URL));

        $headers[] ='Pinpoint-Traceid:'.PerRequestPlugins::instance()->tid;
        $headers[] ='Pinpoint-Pspanid:'.PerRequestPlugins::instance()->sid;
        $nsid = PerRequestPlugins::instance()->generateSpanID();
        $headers[] ='Pinpoint-Spanid:'.$nsid;
    }

    /**
     * Fix the bug when user not set  CURLOPT_URL.
     * @param $ch
     */
    private function handleUrl($ch,$url)
    {
        if(PerRequestPlugins::instance()->traceLimit()){
            \curl_setopt($ch,CURLOPT_HTTPHEADER,array("Pinpoint-Sampled:s0"));
            return ;
        }

        $nsid = PerRequestPlugins::instance()->generateSpanID();
        $header = array(
            'Pinpoint-Sampled:s1',
            'Pinpoint-Flags:0',
            'Pinpoint-Papptype:1500',
            'Pinpoint-Pappname:'.pinpoint_app_name(),
            'Pinpoint-Host:'.$this->getHostFromURL($url),
            'Pinpoint-Traceid:'.PerRequestPlugins::instance()->tid,
            'Pinpoint-Pspanid:'.PerRequestPlugins::instance()->sid,
            'Pinpoint-Spanid:'.$nsid
            );
        \curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
    }


    function onBefore()
    {
        if($this->apId !== 'curl_setopt'){
            return ;
        }

        $argv = &$this->args[0];
        if( isset($argv[1])){
            $ch = $argv[0];

            if($argv[1] == CURLOPT_HTTPHEADER){
                $this->handleHttpHeader($ch,$argv[2]);
            }elseif ($argv[1] == CURLOPT_URL){
                $this->handleUrl($ch,$argv[2]);
            }

            pinpoint_add_clues(PHP_ARGS,"...");
            pinpoint_add_clue("stp",PHP_METHOD);
        }
    }

    function onEnd(&$ret)
    {
        if($this->apId == 'curl_exec'){
            $argv = &$this->args[0];
            $ch = $argv[0];
            pinpoint_add_clue("dst",$this->getHostFromURL(curl_getinfo($ch,CURLINFO_EFFECTIVE_URL)));
            pinpoint_add_clue("stp",PINPOINT_PHP_REMOTE);
            pinpoint_add_clue('nsid',PerRequestPlugins::instance()->getCurNextSpanId());
            pinpoint_add_clues(HTTP_URL,curl_getinfo($ch,CURLINFO_EFFECTIVE_URL));
            pinpoint_add_clues(HTTP_STATUS_CODE,curl_getinfo($ch,CURLINFO_HTTP_CODE));

            //todo http io , while curl not support get the time usage
            //pinpoint_add_clues(HTTP_IO,sprintf("11:[%d,%d,%d,%d]",);
        }
    }

    function onException($e)
    {

    }
    function getHostFromURL(string $url)
    {
        $urlAr   = parse_url($url);
        $retUrl = '';
        if(isset($urlAr['host']))
        {
            $retUrl.=$urlAr['host'];
        }

        if(isset($urlAr['path'])){
            $retUrl.=$urlAr['path'];
        }

        if(isset($urlAr['port']))
        {
            $retUrl .= ":".$urlAr['port'];
        }

        return $retUrl;
    }
}
