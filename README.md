# PWA

(https://hangul.dothome.co.kr/index.php)

프로젝트구성안
/html
  ├── index.php          ← 메인 홈
  ├── intro.php          ← 소개
  ├── public.php         ← 공개글
  ├── notice.php         ← 최신정보
  ├── archive.php        ← 사랑 아카이브
  ├── qna.php            ← 문의하기
  ├── login.php          ← 로그인
  ├── join.php           ← 회원가입
  ├── logout.php         ← 로그아웃
  │
  ├── /board (Summernote)
  │     ├── list.php     ← 게시판 목록
  │     ├── view.php     ← 상세보기
  │     ├── write.php    ← 글쓰기
  │     ├── image_upload.php    ← 이미지 저장
  │     ├── edit.php     ← 글수정
  │     └── delete.php   ← 글삭제
  │
  ├── /cal (fullcalendar)          
  │     ├── cal.php             ← 캘린더 화면
  │     ├── cal_events.php      ← 캘린더 조회
  │     └── cal_save.php        ← 캘린더 저장
  │
  ├── /includes
  │     ├── header.php
  │     ├── footer.php
  │     ├── db.php
  │     └── auth_check.php
  │
  ├── /css
  ├── /js
  └── /uploads