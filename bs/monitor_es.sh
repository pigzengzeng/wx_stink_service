#!/bin/bash

ES_PID=`ps -ef |grep elasticsearch.pid |grep -v 'grep' |awk '{print $2}'`
ESMonitorLog=/opt/logs/es-monitor.log

StartES='/etc/init.d/elasticsearch restart'

Monitor()
{
  if [[ $ES_PID ]];then
    echo "[info]当前ES进程ID为:$ES_PID"
  else
    echo "[error]ES进程不存在!ES开始自动重启..."
    sh $StartES -d
  fi
}
Monitor>>$ESMonitorLog