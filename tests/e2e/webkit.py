"""Smoke test trên engine WebKit thật (WebKitGTK qua WebKitWebDriver + Xvfb). Chạy: xvfb-run -a python3 tests/e2e/webkit.py"""
import sys, time, os; sys.path.insert(0, os.path.join(os.environ.get('PYLIB','.')))
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.remote.webdriver import WebDriver
from selenium.webdriver.common.options import ArgOptions
from selenium.webdriver.chrome.service import Service
import subprocess, os
drv=subprocess.Popen(['WebKitWebDriver','--port=4444'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL); time.sleep(2)
class Opt(ArgOptions):
    def __init__(s):
        super().__init__(); s.set_capability('browserName','MiniBrowser'); s.set_capability('webkitgtk:browserOptions',{'binary':'/usr/lib/x86_64-linux-gnu/webkit2gtk-4.1/MiniBrowser','args':['--automation']})
    @property
    def default_capabilities(s): return {}
try:
    d=WebDriver(command_executor='http://127.0.0.1:4444',options=Opt())
    print('UA:',d.execute_script('return navigator.userAgent'))
    d.set_window_size(390,844)
    d.get('http://127.0.0.1:8088/?dangnhap=1'); time.sleep(2)
    print('title:',d.title)
    d.save_screenshot(os.path.join(os.path.dirname(os.path.abspath(__file__)),'out')+'/webkit-login.png')
    tel=[e for e in d.find_elements(By.CSS_SELECTOR,'input[type=tel]') if e.is_displayed()][0]; tel.send_keys('0901000001')
    pw=[e for e in d.find_elements(By.CSS_SELECTOR,'input[autocomplete=current-password]') if e.is_displayed()][0]; pw.send_keys('tntt@2026')
    d.find_element(By.CSS_SELECTOR,'button[type=submit]').click(); time.sleep(4)
    print('logged in shell:',d.execute_script("return !!document.querySelector('.app-shell')"))
    errs=[]
    for k in ['students','attendance','leave','reports','org','scores']:
        d.execute_script("var a=window.Alpine.$data(document.querySelector('.app-shell'));a.openModule(arguments[0])",k); time.sleep(1.5)
        cur=d.execute_script("return window.Alpine.$data(document.querySelector('.app-shell')).currentModule")
        ovf=d.execute_script("return document.documentElement.scrollWidth-document.documentElement.clientWidth")
        print(k,'->',cur,'overflowX',ovf)
    d.save_screenshot(os.path.join(os.path.dirname(os.path.abspath(__file__)),'out')+'/webkit-app.png')
    d.quit()
except Exception as e:
    print('WEBKIT-FAIL',type(e).__name__,str(e)[:300])
finally:
    drv.terminate()
